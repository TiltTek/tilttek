<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Elimination\Bracket;
use Tilttek\Tournament\Elimination\BracketMatch;
use Tilttek\Tournament\Elimination\BracketState;
use Tilttek\Tournament\Elimination\MatchState;
use Tilttek\Tournament\Elimination\Slot;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Game;
use Tilttek\Tournament\Outcome;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\Records;
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Round;
use Tilttek\Tournament\ScheduledGame;
use Tilttek\Tournament\Scoring;
use Tilttek\Tournament\Stage;
use Tilttek\Tournament\Standings\Standing;
use Tilttek\Tournament\Tournament;
use Tilttek\Tournament\TournamentState;

#[CoversClass(Slot::class)]
#[CoversClass(MatchState::class)]
#[CoversClass(Game::class)]
#[CoversClass(ScheduledGame::class)]
#[CoversClass(Standing::class)]
#[CoversClass(Stage::class)]
#[CoversClass(TournamentState::class)]
#[CoversClass(Records::class)]
#[CoversClass(Round::class)]
#[CoversClass(BracketState::class)]
final class DefaultsTest extends TestCase
{
    public function testASlotKeepsOnlyWhatItsKindNeeds(): void
    {
        $participant = Slot::participant('alice');
        self::assertSame([0, 0], [$participant->rank, $participant->match]);

        $qualifier = Slot::qualifier('pools', 2);
        self::assertSame([2, 0], [$qualifier->rank, $qualifier->match]);

        $winner = Slot::winnerOf(3);
        self::assertSame([0, 3], [$winner->rank, $winner->match]);

        $bye = Slot::bye();
        self::assertSame([0, 0, null, null], [$bye->rank, $bye->match, $bye->participant, $bye->from]);
    }

    public function testAMatchStateStartsWithNobodyAbsent(): void
    {
        $state = new MatchState(new BracketMatch(1, 1, Slot::bye(), Slot::bye()));

        self::assertFalse($state->firstEmpty);
        self::assertFalse($state->secondEmpty);
        self::assertFalse($state->decided);
        self::assertFalse($state->walkover);
    }

    public function testAGameIsNotARestUnlessSaid(): void
    {
        self::assertFalse(new Game(1, 'bob', true, 2)->rest);
        self::assertTrue(new Game(1, null, true, 0, rest: true)->rest);
    }

    public function testAGameNeedsItsOutcomeToHaveBeenPlayed(): void
    {
        self::assertFalse(new Game(1, 'bob', true, 0)->isPlayed());
        self::assertFalse(new Game(1, 'bob', true, 0, Outcome::FirstForfeits)->isPlayed());
        self::assertTrue(new Game(1, 'bob', true, 2, Outcome::FirstWins)->isPlayed());
    }

    public function testAScheduledGameIsPublishedAndNotARestUnlessSaid(): void
    {
        $game = new ScheduledGame('swiss', 1, 'bob', true);

        self::assertTrue($game->published);
        self::assertFalse($game->rest);
        self::assertFalse($game->isBye());
    }

    public function testAStandingIsNotDrawnNorWithdrawnUnlessSaid(): void
    {
        $standing = new Standing('alice', 1, 4);

        self::assertFalse($standing->drawn);
        self::assertFalse($standing->withdrawn);
        self::assertSame([], $standing->tiebreaks);
    }

    public function testTheStagesDefaults(): void
    {
        $swiss = Stage::swiss('swiss', 3);
        self::assertSame([0, 1, 1], [$swiss->qualifiers, $swiss->pools, $swiss->legs]);

        $robin = Stage::roundRobin('pools');
        self::assertSame([1, 1, 0], [$robin->pools, $robin->legs, $robin->qualifiers]);
        self::assertSame(0, $robin->advancing());
    }

    public function testATournamentStateStartsAtVersionZero(): void
    {
        $state = new TournamentState(new Tournament('cup', 'seed', ['a', 'b'], [Stage::swiss('swiss', 1)]), ['a', 'b']);

        self::assertSame(0, $state->version);
        self::assertSame($state->version, $state->with(podium: ['a'])->version);
    }

    public function testAByeIsPlayedFirstAndScoresItsPoints(): void
    {
        $records = Records::build(['a'], [new Round('s', 1, [new Pairing(1, 'a')])], [], new Scoring(bye: 3));

        $game = $records->of('a')->games[0];
        self::assertTrue($game->first);
        self::assertSame(3, $game->points);
        self::assertFalse($game->rest);
    }

    public function testATableWithoutResultOrAByeDoesNotStopTheNextOnes(): void
    {
        $rounds = [new Round('s', 1, [
            new Pairing(1, 'a'),
            new Pairing(2, 'b', 'c'),
            new Pairing(3, 'd', 'e'),
            new Pairing(4, 'f', 'g'),
        ])];
        $records = Records::build(['a', 'b', 'c', 'd', 'e', 'f', 'g'], $rounds, ['s#1#3' => Result::firstWins(), 's#1#4' => Result::secondWins()], new Scoring());

        self::assertSame([2, 0, 0, 2, 0, 0, 2], array_map($records->points(...), ['a', 'b', 'c', 'd', 'e', 'f', 'g']));
    }

    public function testASecondPlayerGetsTheirMission(): void
    {
        $records = Records::build(['a', 'b'], [new Round('s', 1, [new Pairing(1, 'a', 'b')])], ['s#1#1' => Result::firstWins(10, 4)], new Scoring());

        self::assertSame([10, 4], [$records->of('a')->mission(), $records->of('b')->mission()]);
        self::assertSame(1, $records->of('b')->games[0]->round);
    }

    public function testASecondPlayerWithoutMissionScoresNone(): void
    {
        $records = Records::build(['a', 'b'], [new Round('s', 1, [new Pairing(1, 'a', 'b')])], ['s#1#1' => Result::firstWins()], new Scoring());

        self::assertSame(0, $records->of('b')->games[0]->mission);
        self::assertFalse($records->of('b')->games[0]->first);
        self::assertTrue($records->of('a')->games[0]->first);
    }

    public function testTheTablesOfARoundAreEachKeptSeparately(): void
    {
        $this->expectException(InvalidTournament::class);
        new Round('s', 1, [new Pairing(1, 'a', 'b'), new Pairing(2, 'c', 'd'), new Pairing(1, 'e', 'f')]);
    }

    public function testAParticipantSitsAtOneTableOnly(): void
    {
        $this->expectException(InvalidTournament::class);
        new Round('s', 1, [new Pairing(1, 'a', 'b'), new Pairing(2, 'c', 'b')]);
    }

    public function testAParticipantSeatedFirstAndAgainSecondIsRefused(): void
    {
        $this->expectException(InvalidTournament::class);
        new Round('s', 1, [new Pairing(1, 'a', 'b'), new Pairing(2, 'b', 'c')]);
    }

    public function testAnOpenMatchHasNoAbsentPlace(): void
    {
        $state = BracketState::of(Bracket::seeded([Slot::participant('a'), Slot::participant('b'), Slot::participant('c'), Slot::participant('d')]), [], [1 => Result::firstWins()]);

        $played = $state->state(1);
        self::assertInstanceOf(MatchState::class, $played);
        self::assertFalse($played->firstEmpty);
        self::assertFalse($played->secondEmpty);
        self::assertSame('a', $played->winner);
        self::assertTrue($played->decided);

        $final = $state->state(3);
        self::assertInstanceOf(MatchState::class, $final);
        self::assertFalse($final->firstEmpty);
        self::assertFalse($final->secondEmpty);
        self::assertSame('a', $final->first);
        self::assertNull($final->second);
    }

    public function testAMatchOfWinnersNotYetDecidedIsNotEmpty(): void
    {
        $state = BracketState::of(Bracket::seeded([Slot::participant('a'), Slot::participant('b'), Slot::participant('c'), Slot::participant('d')]));

        $final = $state->state(3);
        self::assertInstanceOf(MatchState::class, $final);
        self::assertFalse($final->firstEmpty);
        self::assertFalse($final->secondEmpty);
        self::assertFalse($final->decided);
    }
}
