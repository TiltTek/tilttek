<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit\Tiebreak;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Contract\Tiebreaker;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\Records;
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Round;
use Tilttek\Tournament\Scoring;
use Tilttek\Tournament\Tiebreak\Buchholz;
use Tilttek\Tournament\Tiebreak\HeadToHead;
use Tilttek\Tournament\Tiebreak\MissionPoints;
use Tilttek\Tournament\Tiebreak\SonnebornBerger;
use Tilttek\Tournament\Tiebreak\Tiebreakers;
use Tilttek\Tournament\Tiebreak\Wins;

#[CoversClass(Buchholz::class)]
#[CoversClass(SonnebornBerger::class)]
#[CoversClass(HeadToHead::class)]
#[CoversClass(Wins::class)]
#[CoversClass(MissionPoints::class)]
#[CoversClass(Tiebreakers::class)]
final class TiebreakersTest extends TestCase
{
    /**
     * A pool of four, written by hand, the default scoring: a beats b, c and d draw; a and c draw, b beats d; d beats a,
     * c beats b. Points: a 3, b 2, c 4, d 3; a bye for e in each round.
     */
    private function pool(): Records
    {
        return Records::build(['a', 'b', 'c', 'd', 'e'], [
            new Round('s', 1, [new Pairing(1, 'a', 'b'), new Pairing(2, 'c', 'd'), new Pairing(3, 'e')]),
            new Round('s', 2, [new Pairing(1, 'a', 'c'), new Pairing(2, 'b', 'd')]),
            new Round('s', 3, [new Pairing(1, 'd', 'a'), new Pairing(2, 'b', 'c')]),
        ], [
            's#1#1' => Result::firstWins(10, 2),
            's#1#2' => Result::draw(4, 6),
            's#2#1' => Result::draw(3, 3),
            's#2#2' => Result::firstWins(9, 1),
            's#3#1' => Result::firstWins(8, 0),
            's#3#2' => Result::secondWins(5, 7),
        ], new Scoring());
    }

    public function testBuchholzAddsThePointsOfTheOpponents(): void
    {
        $scores = new Buchholz()->scores(['a', 'b', 'c', 'd', 'e'], $this->pool());

        self::assertSame(['a' => 9, 'b' => 10, 'c' => 8, 'd' => 9, 'e' => 0], $scores);
        self::assertSame('buchholz', new Buchholz()->id());
    }

    public function testTheCutBuchholzLeavesOutTheLowestOpponents(): void
    {
        self::assertSame(['a' => 7, 'b' => 7, 'c' => 6], new Buchholz(1)->scores(['a', 'b', 'c'], $this->pool()));
        self::assertSame(['a' => 4], new Buchholz(2)->scores(['a'], $this->pool()));
        self::assertSame(['a' => 0], new Buchholz(4)->scores(['a'], $this->pool()));
        self::assertSame('buchholz-cut-1', new Buchholz(1)->id());
        self::assertSame('buchholz-cut-2', new Buchholz(2)->id());
    }

    public function testTheCutIsZeroToFour(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('A cut Buchholz leaves out 0 to 4 opponents.');
        new Buchholz(5);
    }

    public function testTheCutIsNeverNegative(): void
    {
        $this->expectException(InvalidTournament::class);
        new Buchholz(-1);
    }

    public function testSonnebornBergerWeighsEachOpponentByThePointsScoredAgainstThem(): void
    {
        self::assertSame(['a' => 8, 'd' => 10, 'e' => 0], new SonnebornBerger()->scores(['a', 'd', 'e'], $this->pool()));
        self::assertSame('sonneborn-berger', new SonnebornBerger()->id());
    }

    public function testTheHeadToHeadReadsTheMatchesInsideTheGroupAlone(): void
    {
        self::assertSame(['a' => 0, 'd' => 2], new HeadToHead()->scores(['a', 'd'], $this->pool()));
        self::assertSame(['a' => 3, 'b' => 0, 'c' => 3], new HeadToHead()->scores(['a', 'b', 'c'], $this->pool()));
        self::assertSame('head-to-head', new HeadToHead()->id());
    }

    public function testWinsCountTheMatchesWonOverTheBoard(): void
    {
        self::assertSame(['a' => 1, 'c' => 1, 'e' => 0], new Wins()->scores(['a', 'c', 'e'], $this->pool()));
        self::assertSame('wins', new Wins()->id());
    }

    public function testMissionPointsAddUp(): void
    {
        self::assertSame(['a' => 13, 'b' => 2 + 9 + 5, 'c' => 4 + 3 + 7, 'd' => 6 + 1 + 8], new MissionPoints()->scores(['a', 'b', 'c', 'd'], $this->pool()));
        self::assertSame('mission-points', new MissionPoints()->id());
    }

    public function testTheRegistryGivesTheTiebreakersInTheOrderAsked(): void
    {
        $registry = new Tiebreakers();
        $tiebreakers = $registry->get(['sonneborn-berger', 'buchholz-cut-1', 'buchholz', 'buchholz-cut-2', 'head-to-head', 'wins', 'mission-points']);

        self::assertSame(['sonneborn-berger', 'buchholz-cut-1', 'buchholz', 'buchholz-cut-2', 'head-to-head', 'wins', 'mission-points'], array_map(static fn (Tiebreaker $tiebreaker): string => $tiebreaker->id(), $tiebreakers));
        self::assertSame([], $registry->get([]));
        self::assertTrue($registry->has('wins'));
        self::assertFalse($registry->has('koya'));
    }

    public function testAnApplicationAddsItsOwnTiebreakers(): void
    {
        $registry = new Tiebreakers($this->custom('koya'));

        self::assertSame('koya', $registry->get(['koya'])[0]->id());
    }

    public function testAnUnknownTiebreakerIsRefused(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('No tie-breaker has the code koya.');
        new Tiebreakers()->get(['koya']);
    }

    public function testTwoTiebreakersNeverShareACode(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('Two tie-breakers have the code wins.');
        new Tiebreakers($this->custom('wins'));
    }

    public function testTheCodeOfATiebreakerIsLowercase(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The code of a tie-breaker is 1 to 64 lowercase letters, digits or "-", starting with a letter; "Koya" is not.');
        new Tiebreakers($this->custom('Koya'));
    }

    public function testTheCodeOfATiebreakerEndsWhereItSeemsTo(): void
    {
        $this->expectException(InvalidTournament::class);
        new Tiebreakers($this->custom("koya\n"));
    }

    public function testTheCodeOfATiebreakerIsShort(): void
    {
        self::assertTrue(new Tiebreakers($this->custom('k'.str_repeat('a', 63)))->has('k'.str_repeat('a', 63)));
        $this->expectException(InvalidTournament::class);
        new Tiebreakers($this->custom('k'.str_repeat('a', 64)));
    }

    private function custom(string $id): Tiebreaker
    {
        return new readonly class($id) implements Tiebreaker {
            public function __construct(private string $id)
            {
            }

            #[Override]
            public function id(): string
            {
                return $this->id;
            }

            #[Override]
            public function scores(array $tied, Records $records): array
            {
                return [];
            }
        };
    }
}
