<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit\Standings;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Contract\Tiebreaker;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\Records;
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Round;
use Tilttek\Tournament\Scoring;
use Tilttek\Tournament\SeededDraw;
use Tilttek\Tournament\Standings\Standing;
use Tilttek\Tournament\Standings\StandingChange;
use Tilttek\Tournament\Standings\Standings;
use Tilttek\Tournament\Standings\StandingsCalculator;
use Tilttek\Tournament\Tiebreak\Buchholz;
use Tilttek\Tournament\Tiebreak\HeadToHead;
use Tilttek\Tournament\Tiebreak\SonnebornBerger;
use Tilttek\Tournament\Tiebreak\Wins;

#[CoversClass(StandingsCalculator::class)]
#[CoversClass(Standings::class)]
#[CoversClass(Standing::class)]
#[CoversClass(StandingChange::class)]
#[CoversClass(SeededDraw::class)]
final class StandingsTest extends TestCase
{
    /**
     * The pool of four of the tie-breakers' test: a 3, b 2, c 4, d 3; a and d tie on Buchholz (9), d wins on
     * Sonneborn-Berger (10 against 8).
     */
    private function pool(): Records
    {
        return Records::build(['a', 'b', 'c', 'd'], [
            new Round('s', 1, [new Pairing(1, 'a', 'b'), new Pairing(2, 'c', 'd')]),
            new Round('s', 2, [new Pairing(1, 'a', 'c'), new Pairing(2, 'b', 'd')]),
            new Round('s', 3, [new Pairing(1, 'd', 'a'), new Pairing(2, 'b', 'c')]),
        ], [
            's#1#1' => Result::firstWins(),
            's#1#2' => Result::draw(),
            's#2#1' => Result::draw(),
            's#2#2' => Result::firstWins(),
            's#3#1' => Result::firstWins(),
            's#3#2' => Result::secondWins(),
        ], new Scoring());
    }

    public function testTheTiebreakersApplyInTheOrderDeclared(): void
    {
        $standings = new StandingsCalculator([new Buchholz(), new SonnebornBerger()], 'seed')->compute(['a', 'b', 'c', 'd'], $this->pool(), ['b' => true]);

        self::assertSame(['c', 'd', 'a', 'b'], $standings->participants());
        self::assertSame([1, 2, 3, 4], array_map(static fn (Standing $standing): int => $standing->rank, $standings->standings));
        self::assertSame([4, 3, 3, 2], array_map(static fn (Standing $standing): int => $standing->points, $standings->standings));
        self::assertSame(['buchholz' => 9, 'sonneborn-berger' => 10], $standings->of('d')?->tiebreaks);
        self::assertSame(['buchholz' => 9, 'sonneborn-berger' => 8], $standings->of('a')?->tiebreaks);
        self::assertSame([], $standings->of('c')?->tiebreaks, 'A participant alone on their points needs no tie-breaker.');
        self::assertFalse($standings->of('d')->drawn);
        self::assertNotNull($standings->of('b'));
        self::assertTrue($standings->of('b')->withdrawn);
        self::assertFalse($standings->of('a')->withdrawn);
        self::assertNull($standings->of('e'));
    }

    public function testATieLeftAtTheEndIsBrokenByTheSeededDraw(): void
    {
        $standings = new StandingsCalculator([new Wins()], 'seed-7')->compute(['a', 'b', 'c', 'd'], $this->pool());
        $drawn = SeededDraw::order('seed-7', ['a', 'd']);

        self::assertSame(['c', ...$drawn, 'b'], $standings->participants());
        self::assertTrue($standings->of('a')?->drawn);
        self::assertTrue($standings->of('d')?->drawn);
        self::assertFalse($standings->of('c')?->drawn);
        self::assertSame(['wins' => 1], $standings->of('a')->tiebreaks);
    }

    public function testWithoutTiebreakersTheDrawDecidesAtOnce(): void
    {
        $standings = new StandingsCalculator([], 'other')->compute(['a', 'b', 'c', 'd'], $this->pool());

        self::assertSame(['c', ...SeededDraw::order('other', ['a', 'd']), 'b'], $standings->participants());
        self::assertSame([], $standings->of('a')?->tiebreaks);
    }

    public function testEachTiebreakerIsComputedOnTheGroupStillTied(): void
    {
        $spy = new class implements Tiebreaker {
            /** @var list<list<string>> */
            public array $groups = [];

            #[Override]
            public function id(): string
            {
                return 'spy';
            }

            #[Override]
            public function scores(array $tied, Records $records): array
            {
                $this->groups[] = $tied;

                return array_fill_keys($tied, 0);
            }
        };
        $records = Records::build(['w', 'x', 'y', 'z'], [
            new Round('s', 1, [new Pairing(1, 'w', 'x'), new Pairing(2, 'y', 'z')]),
            new Round('s', 2, [new Pairing(1, 'w', 'y'), new Pairing(2, 'x', 'z')]),
        ], [
            's#1#1' => Result::draw(),
            's#1#2' => Result::draw(),
            's#2#1' => Result::firstWins(),
            's#2#2' => Result::secondWins(),
        ], new Scoring());
        // Points: w 3, z 3, x 1, y 1. Neither pair met: the head-to-head splits nothing, and the spy sees each pair whole.
        $standings = new StandingsCalculator([new HeadToHead(), $spy], 'seed')->compute(['w', 'x', 'y', 'z'], $records);

        self::assertSame([['w', 'z'], ['x', 'y']], $spy->groups);
        self::assertSame(['head-to-head' => 0, 'spy' => 0], $standings->of('w')?->tiebreaks);
        self::assertSame(['head-to-head' => 0, 'spy' => 0], $standings->of('x')?->tiebreaks);
    }

    public function testAGroupSplitByATiebreakerGoesOnInSmallerGroups(): void
    {
        $first = new class implements Tiebreaker {
            #[Override]
            public function id(): string
            {
                return 'first';
            }

            #[Override]
            public function scores(array $tied, Records $records): array
            {
                return ['p' => 1, 'q' => 5, 'r' => 1, 's' => 1, 't' => 0];
            }
        };
        $spy = new class implements Tiebreaker {
            /** @var list<list<string>> */
            public array $groups = [];

            #[Override]
            public function id(): string
            {
                return 'spy';
            }

            #[Override]
            public function scores(array $tied, Records $records): array
            {
                $this->groups[] = $tied;

                return array_combine($tied, range(1, \count($tied)));
            }
        };
        $standings = new StandingsCalculator([$first, $spy], 'seed')->compute(['p', 'q', 'r', 's', 't'], Records::build([], [], [], new Scoring()));

        self::assertSame([['p', 'r', 's']], $spy->groups);
        self::assertSame(['q', 's', 'r', 'p', 't'], $standings->participants());
        self::assertSame(['first' => 1, 'spy' => 3], $standings->of('s')?->tiebreaks);
        self::assertSame(['first' => 5], $standings->of('q')?->tiebreaks);
    }

    public function testAMissingScoreCountsAsZero(): void
    {
        $partial = new class implements Tiebreaker {
            #[Override]
            public function id(): string
            {
                return 'partial';
            }

            #[Override]
            public function scores(array $tied, Records $records): array
            {
                return ['d' => -1];
            }
        };
        $standings = new StandingsCalculator([$partial], 'seed')->compute(['a', 'd'], $this->pool());

        self::assertSame(['a', 'd'], $standings->participants());
        self::assertSame(['partial' => 0], $standings->of('a')?->tiebreaks);
    }

    public function testTheQualifiersAreTheFirstStillIn(): void
    {
        $standings = new Standings([
            new Standing('a', 1, 6),
            new Standing('b', 2, 4, withdrawn: true),
            new Standing('c', 3, 4),
            new Standing('d', 4, 0),
        ]);

        self::assertSame(['a', 'c'], $standings->qualifiers(2));
        self::assertSame(['a', 'c', 'd'], $standings->qualifiers(5));
        self::assertSame([], $standings->qualifiers(0));
        self::assertSame([], $standings->qualifiers(-1));
    }

    public function testTheChangesBetweenTwoStandingsAreListed(): void
    {
        $before = new Standings([new Standing('a', 1, 6), new Standing('b', 2, 4), new Standing('c', 3, 2), new Standing('e', 4, 1)]);
        $after = new Standings([new Standing('b', 1, 6), new Standing('a', 2, 4), new Standing('c', 3, 2), new Standing('d', 4, 2), new Standing('f', 5, 1)]);
        $changes = $after->since($before);

        self::assertCount(5, $changes);
        self::assertSame(['b', 'a', 'd', 'f', 'e'], array_map(static fn (StandingChange $change): string => $change->participant, $changes));
        self::assertSame([2, 1, 4, 6], [$changes[0]->rankBefore, $changes[0]->rankAfter, $changes[0]->pointsBefore, $changes[0]->pointsAfter]);
        self::assertSame([null, 4, null, 2], [$changes[2]->rankBefore, $changes[2]->rankAfter, $changes[2]->pointsBefore, $changes[2]->pointsAfter]);
        self::assertSame([4, null, 1, null], [$changes[4]->rankBefore, $changes[4]->rankAfter, $changes[4]->pointsBefore, $changes[4]->pointsAfter]);
        self::assertSame([], $before->since($before));
    }

    public function testAChangeOfPointsAloneIsAChange(): void
    {
        $changes = new Standings([new Standing('a', 1, 5)])->since(new Standings([new Standing('a', 1, 4)]));

        self::assertCount(1, $changes);
        self::assertSame(4, $changes[0]->pointsBefore);
    }

    public function testTheSeededDrawIsTheOrderOfTheHashes(): void
    {
        $participants = ['p1', 'p2', 'p3', 'p4', 'p5'];
        $expected = $participants;
        usort($expected, static fn (string $a, string $b): int => strcmp(hash('sha256', "s\0".$a), hash('sha256', "s\0".$b)));

        self::assertSame($expected, SeededDraw::order('s', $participants));
        self::assertSame(SeededDraw::order('s', array_reverse($participants)), SeededDraw::order('s', $participants));
        self::assertSame(hash('sha256', "s\0p1"), SeededDraw::key('s', 'p1'));
        self::assertNotSame(SeededDraw::order('s', $participants), SeededDraw::order('t', $participants));
    }
}
