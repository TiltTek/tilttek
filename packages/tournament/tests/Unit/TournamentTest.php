<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Round;
use Tilttek\Tournament\Scoring;
use Tilttek\Tournament\Stage;
use Tilttek\Tournament\StageFormat;
use Tilttek\Tournament\Submission;
use Tilttek\Tournament\Tournament;
use Tilttek\Tournament\TournamentState;

#[CoversClass(Stage::class)]
#[CoversClass(StageFormat::class)]
#[CoversClass(Tournament::class)]
#[CoversClass(TournamentState::class)]
#[CoversClass(Submission::class)]
final class TournamentTest extends TestCase
{
    public function testTheNamedStages(): void
    {
        $swiss = Stage::swiss('swiss', 5, 8);
        self::assertSame([StageFormat::Swiss, 5, 1, 1, 8, 8], [$swiss->format, $swiss->rounds, $swiss->pools, $swiss->legs, $swiss->qualifiers, $swiss->advancing()]);

        $pools = Stage::roundRobin('pools', 4, 2, 2);
        self::assertSame([StageFormat::RoundRobin, 0, 4, 2, 2, 8], [$pools->format, $pools->rounds, $pools->pools, $pools->legs, $pools->qualifiers, $pools->advancing()]);

        $cut = Stage::singleElimination('cut');
        self::assertSame([StageFormat::SingleElimination, 0, 1, 1, 0], [$cut->format, $cut->rounds, $cut->pools, $cut->legs, $cut->qualifiers]);
        self::assertSame('cut', $cut->id);
        self::assertSame(64, Stage::swiss('s', 64)->rounds);
        self::assertSame(26, Stage::roundRobin('p', 26)->pools);
    }

    /**
     * @return iterable<string, array{callable(): Stage, string}>
     */
    public static function badStages(): iterable
    {
        yield 'no round' => [static fn (): Stage => Stage::swiss('s', 0), 'A Swiss stage has 1 to 64 rounds.'];
        yield 'too many rounds' => [static fn (): Stage => Stage::swiss('s', 65), 'A Swiss stage has 1 to 64 rounds.'];
        yield 'no pool' => [static fn (): Stage => Stage::roundRobin('p', 0), 'A round robin has 1 to 26 pools; the other stages have one.'];
        yield 'too many pools' => [static fn (): Stage => Stage::roundRobin('p', 27), 'A round robin has 1 to 26 pools; the other stages have one.'];
        yield 'pools in a Swiss stage' => [static fn (): Stage => new Stage('s', StageFormat::Swiss, 3, 2), 'A round robin has 1 to 26 pools; the other stages have one.'];
        yield 'three legs' => [static fn (): Stage => Stage::roundRobin('p', 1, 3), 'A round robin is played in 1 or 2 legs; the other stages in one.'];
        yield 'no leg' => [static fn (): Stage => Stage::roundRobin('p', 1, 0), 'A round robin is played in 1 or 2 legs; the other stages in one.'];
        yield 'two legs of Swiss' => [static fn (): Stage => new Stage('s', StageFormat::Swiss, 3, 1, 2), 'A round robin is played in 1 or 2 legs; the other stages in one.'];
        yield 'negative qualifiers' => [static fn (): Stage => Stage::swiss('s', 3, -1), 'The qualifiers of a stage are 0 or more, and an elimination has none: it ends the tournament.'];
        yield 'qualifiers of an elimination' => [static fn (): Stage => new Stage('e', StageFormat::SingleElimination, qualifiers: 2), 'The qualifiers of a stage are 0 or more, and an elimination has none: it ends the tournament.'];
        yield 'a bad identifier' => [static fn (): Stage => Stage::singleElimination('1'), 'The identifier of a stage is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter.'];
    }

    /**
     * @param callable(): Stage $make
     */
    #[DataProvider('badStages')]
    public function testAStageIsInRange(callable $make, string $message): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs($message);
        $make();
    }

    public function testATournamentHoldsItsDeclaration(): void
    {
        $tournament = new Tournament('cup', 'seed-2026', ['a', 'b', 'c'], [Stage::swiss('swiss', 3)], Scoring::threeForAWin(), ['buchholz']);

        self::assertSame('cup', $tournament->id);
        self::assertSame('seed-2026', $tournament->seed);
        self::assertSame(['a', 'b', 'c'], $tournament->participants);
        self::assertSame(3, $tournament->scoring->win);
        self::assertSame(['buchholz'], $tournament->tiebreakers);
        self::assertSame([$tournament->stages[0], 0], $tournament->stage('swiss'));
        self::assertSame(2, new Tournament('cup', 's', ['a', 'b'], [Stage::swiss('swiss', 1)])->scoring->win);
    }

    public function testTheStagesFollowEachOther(): void
    {
        $tournament = new Tournament('cup', 's', ['a', 'b', 'c', 'd', 'e', 'f'], [Stage::roundRobin('pools', 2, qualifiers: 2), Stage::swiss('swiss', 2, 2), Stage::singleElimination('final')]);

        self::assertSame(2, $tournament->stage('final')[1]);
        self::assertSame(1, $tournament->stage('swiss')[1]);
    }

    public function testAnUnknownStageIsRefused(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The tournament has no stage nope.');
        new Tournament('cup', 's', ['a', 'b'], [Stage::swiss('swiss', 1)])->stage('nope');
    }

    /**
     * @return iterable<string, array{callable(): Tournament, string}>
     */
    public static function badTournaments(): iterable
    {
        $swiss = [Stage::swiss('swiss', 3)];

        yield 'a bad identifier' => [static fn (): Tournament => new Tournament('1', 's', ['a', 'b'], $swiss), 'The identifier of a tournament is 1 to 128'];
        yield 'an empty seed' => [static fn (): Tournament => new Tournament('cup', '', ['a', 'b'], $swiss), 'The seed of a tournament is 1 to 128 printable ASCII characters, without spaces.'];
        yield 'a seed with a space' => [static fn (): Tournament => new Tournament('cup', 'a b', ['a', 'b'], $swiss), 'The seed of a tournament'];
        yield 'a seed with a line break after' => [static fn (): Tournament => new Tournament('cup', "ab\n", ['a', 'b'], $swiss), 'The seed of a tournament'];
        yield 'a long seed' => [static fn (): Tournament => new Tournament('cup', str_repeat('x', 129), ['a', 'b'], $swiss), 'The seed of a tournament'];
        yield 'one participant' => [static fn (): Tournament => new Tournament('cup', 's', ['a'], $swiss), 'A tournament has 2 to 512 participants.'];
        yield 'too many participants' => [static fn (): Tournament => new Tournament('cup', 's', array_map(static fn (int $n): string => 'p'.$n, range(1, 513)), $swiss), 'A tournament has 2 to 512 participants.'];
        yield 'a bad participant' => [static fn (): Tournament => new Tournament('cup', 's', ['a', '2'], $swiss), 'The identifier of a participant'];
        yield 'a participant twice' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b', 'a'], $swiss), 'A participant is given once.'];
        yield 'no stage' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b'], []), 'A tournament has 1 to 4 stages.'];
        yield 'five stages' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b', 'c'], [Stage::swiss('s1', 1, 2), Stage::swiss('s2', 1, 2), Stage::swiss('s3', 1, 2), Stage::swiss('s4', 1, 2), Stage::swiss('s5', 1)]), 'A tournament has 1 to 4 stages.'];
        yield 'two stages of one name' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b', 'c'], [Stage::swiss('s', 1, 2), Stage::swiss('s', 1)]), 'Two stages have the identifier s.'];
        yield 'an elimination before another stage' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b', 'c'], [Stage::singleElimination('e'), Stage::swiss('s', 1)]), 'An elimination ends the tournament: it is the last stage.'];
        yield 'one qualifier' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b', 'c'], [Stage::swiss('s', 1, 1), Stage::singleElimination('e')]), 'The stage s sends on 2 participants or more, and no more than it has.'];
        yield 'more qualifiers than participants' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b', 'c'], [Stage::swiss('s', 1, 4), Stage::singleElimination('e')]), 'The stage s sends on 2 participants or more, and no more than it has.'];
        yield 'pools too small' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b', 'c'], [Stage::roundRobin('p', 2)]), 'The stage p has pools of 2 participants at least.'];
        yield 'pools too small after a stage' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b', 'c', 'd', 'e'], [Stage::swiss('s', 1, 3), Stage::roundRobin('p', 2)]), 'The stage p has pools of 2 participants at least.'];
        yield 'nine tie-breakers' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b'], $swiss, tiebreakers: ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i']), 'A tournament declares 8 tie-breakers at most, each once.'];
        yield 'a tie-breaker twice' => [static fn (): Tournament => new Tournament('cup', 's', ['a', 'b'], $swiss, tiebreakers: ['wins', 'wins']), 'A tournament declares 8 tie-breakers at most, each once.'];
    }

    /**
     * @param callable(): Tournament $make
     */
    #[DataProvider('badTournaments')]
    public function testATournamentIsInRange(callable $make, string $message): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIsOrContains($message);
        $make();
    }

    public function testTheLimitsOfATournamentAreAccepted(): void
    {
        $tournament = new Tournament('cup', str_repeat('~', 128), array_map(static fn (int $n): string => 'p'.$n, range(1, 512)), [Stage::swiss('s1', 1, 2), Stage::swiss('s2', 1, 2), Stage::swiss('s3', 1, 2), Stage::swiss('s4', 1)], tiebreakers: ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']);

        self::assertCount(512, $tournament->participants);
        self::assertCount(4, $tournament->stages);
        self::assertCount(8, $tournament->tiebreakers);
        self::assertCount(2, new Tournament('cup', '!', ['a', 'b', 'c', 'd'], [Stage::roundRobin('p', 2, qualifiers: 2), Stage::singleElimination('e')])->stages);
        self::assertCount(2, new Tournament('cup', 's', ['a', 'b', 'c'], [Stage::swiss('s', 1, 3), Stage::singleElimination('e')])->stages);
    }

    public function testAStateFindsItsTablesAndRounds(): void
    {
        $tournament = new Tournament('cup', 's', ['a', 'b', 'c'], [Stage::swiss('swiss', 2)]);
        $first = new Round('swiss', 1, [new Pairing(1, 'a', 'b'), new Pairing(2, 'c')]);
        $other = new Round('other', 1, [new Pairing(1, 'a', 'c')]);
        $state = new TournamentState($tournament, ['a', 'b', 'c'], [$first, $other], ['swiss#1#1' => new Submission('a', Result::firstWins())], [], ['c', 'b'], [], 3);

        self::assertSame([$first], $state->roundsOf('swiss'));
        self::assertSame([$other], $state->roundsOf('other'));
        self::assertSame([$first, $first->pairings[1]], $state->table('swiss#1#2'));
        self::assertSame([$other, $other->pairings[0]], $state->table('other#1#1'));
        self::assertNull($state->table('swiss#1#3'));
        self::assertSame(['c' => true, 'b' => true], $state->withdrawnSet());
        self::assertFalse($state->isOver());
        self::assertSame('a', $state->results['swiss#1#1']->by);

        $over = $state->with(podium: ['a', 'b']);
        self::assertTrue($over->isOver());
        self::assertSame(3, $over->version);
        self::assertSame($state->rounds, $over->rounds);
        self::assertSame($state->results, $over->results);
        self::assertSame($state->withdrawn, $over->withdrawn);
        self::assertSame(['a', 'b', 'c'], $over->order);

        $next = $over->withVersion(4);
        self::assertSame(4, $next->version);
        self::assertSame(['a', 'b'], $next->podium);
        self::assertSame($state->rounds, $next->rounds);
        self::assertSame($state->withdrawn, $next->withdrawn);
        self::assertSame($state->results, $next->results);
        self::assertSame($tournament, $next->tournament);

        $changed = $state->with([$other], [], ['x' => []], ['a'], ['b']);
        self::assertSame([[$other], [], ['x' => []], ['a'], ['b'], 3], [$changed->rounds, $changed->results, $changed->conflicts, $changed->withdrawn, $changed->podium, $changed->version]);
        self::assertSame([], $state->with()->conflicts);
    }
}
