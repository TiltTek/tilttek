<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Exception\InvalidResult;
use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Outcome;
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Scoring;

#[CoversClass(Outcome::class)]
#[CoversClass(Result::class)]
#[CoversClass(Scoring::class)]
#[CoversClass(InvalidResult::class)]
#[CoversClass(InvalidTournament::class)]
final class ResultTest extends TestCase
{
    /**
     * @return iterable<string, array{Outcome, bool, bool, bool}>
     */
    public static function outcomes(): iterable
    {
        yield 'first wins' => [Outcome::FirstWins, true, true, false];
        yield 'second wins' => [Outcome::SecondWins, true, false, true];
        yield 'draw' => [Outcome::Draw, true, false, false];
        yield 'first forfeits' => [Outcome::FirstForfeits, false, false, true];
        yield 'second forfeits' => [Outcome::SecondForfeits, false, true, false];
        yield 'both forfeit' => [Outcome::BothForfeit, false, false, false];
    }

    #[DataProvider('outcomes')]
    public function testAnOutcomeTellsWhetherItWasPlayedAndWhoWon(Outcome $outcome, bool $played, bool $first, bool $second): void
    {
        self::assertSame($played, $outcome->isPlayed());
        self::assertSame($first, $outcome->firstWins());
        self::assertSame($second, $outcome->secondWins());
    }

    public function testTheNamedConstructorsGiveTheirOutcomeAndMissionPoints(): void
    {
        self::assertSame(Outcome::FirstWins, Result::firstWins()->outcome);
        self::assertSame(Outcome::SecondWins, Result::secondWins()->outcome);
        self::assertSame(Outcome::Draw, Result::draw()->outcome);
        $result = Result::firstWins(15, 7);
        self::assertSame(15, $result->firstMission);
        self::assertSame(7, $result->secondMission);
        self::assertSame(3, Result::secondWins(0, 3)->secondMission);
        self::assertSame(4, Result::draw(4, 4)->firstMission);
    }

    public function testTwoResultsAreEqualWhenTheirOutcomeAndMissionPointsAre(): void
    {
        self::assertTrue(Result::firstWins(3, 1)->equals(Result::firstWins(3, 1)));
        self::assertFalse(Result::firstWins(3, 1)->equals(Result::secondWins(3, 1)));
        self::assertFalse(Result::firstWins(3, 1)->equals(Result::firstWins(2, 1)));
        self::assertFalse(Result::firstWins(3, 1)->equals(Result::firstWins(3, 2)));
        self::assertFalse(Result::firstWins()->equals(Result::firstWins(0, 0)));
    }

    public function testMissionPointsAreGivenForBothSidesOrNeither(): void
    {
        $this->expectException(InvalidResult::class);
        $this->expectExceptionMessageIs('Both sides of a match have mission points, or neither has.');
        new Result(Outcome::Draw, 3);
    }

    public function testMissionPointsAreGivenForTheSecondSideAlone(): void
    {
        $this->expectException(InvalidResult::class);
        new Result(Outcome::Draw, null, 3);
    }

    public function testMissionPointsAreAtMostTheirLimit(): void
    {
        self::assertSame(Result::MAX_MISSION_POINTS, Result::firstWins(Result::MAX_MISSION_POINTS, 0)->firstMission);
        $this->expectException(InvalidResult::class);
        $this->expectExceptionMessageIs('The mission points of a side are 0 to 1000000.');
        Result::firstWins(0, Result::MAX_MISSION_POINTS + 1);
    }

    public function testMissionPointsAreNeverNegative(): void
    {
        $this->expectException(InvalidResult::class);
        Result::firstWins(-1, 0);
    }

    public function testTheDefaultScoringCountsTwoOneAndZero(): void
    {
        $scoring = new Scoring();
        self::assertSame([2, 0], $scoring->points(Outcome::FirstWins));
        self::assertSame([0, 2], $scoring->points(Outcome::SecondWins));
        self::assertSame([1, 1], $scoring->points(Outcome::Draw));
        self::assertSame([0, 2], $scoring->points(Outcome::FirstForfeits));
        self::assertSame([2, 0], $scoring->points(Outcome::SecondForfeits));
        self::assertSame([0, 0], $scoring->points(Outcome::BothForfeit));
        self::assertSame(2, $scoring->byePoints());
    }

    public function testThreeForAWin(): void
    {
        $scoring = Scoring::threeForAWin();
        self::assertSame([3, 0], $scoring->points(Outcome::FirstWins));
        self::assertSame([1, 1], $scoring->points(Outcome::Draw));
        self::assertSame(3, $scoring->byePoints());
    }

    public function testByesAndForfeitsMayBeSetApart(): void
    {
        $scoring = new Scoring(3, 1, 0, 1, 2, -0);
        self::assertSame(1, $scoring->byePoints());
        self::assertSame([0, 2], $scoring->points(Outcome::FirstForfeits));
        self::assertSame([2, 0], $scoring->points(Outcome::SecondForfeits));
        $penalised = new Scoring(3, 2, 1, forfeitLoss: 0);
        self::assertSame([0, 0], $penalised->points(Outcome::BothForfeit));
        self::assertSame([0, 3], $penalised->points(Outcome::FirstForfeits));
        self::assertSame([3, 0], $penalised->points(Outcome::SecondForfeits));
        self::assertSame([1, 3], $penalised->points(Outcome::SecondWins));
    }

    public function testTheLimitsOfTheScoringAreAccepted(): void
    {
        $scoring = new Scoring(Scoring::MAX_POINTS, Scoring::MAX_POINTS, Scoring::MAX_POINTS, 0, 0, 0);
        self::assertSame(Scoring::MAX_POINTS, $scoring->loss);
    }

    /**
     * @return iterable<string, array{int, int, int, ?int, ?int, ?int}>
     */
    public static function badScorings(): iterable
    {
        yield 'negative loss' => [2, 1, -1, null, null, null];
        yield 'win above the limit' => [1001, 1, 0, null, null, null];
        yield 'negative bye' => [2, 1, 0, -1, null, null];
        yield 'bye above the limit' => [2, 1, 0, 1001, null, null];
        yield 'negative forfeit win' => [2, 1, 0, null, -1, null];
        yield 'negative forfeit loss' => [2, 1, 0, null, null, -1];
        yield 'forfeit loss above the limit' => [2, 1, 0, null, null, 1001];
    }

    #[DataProvider('badScorings')]
    public function testThePointsOfAMatchAreInRange(int $win, int $draw, int $loss, ?int $bye, ?int $forfeitWin, ?int $forfeitLoss): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('The points of a match are 0 to 1000.');
        new Scoring($win, $draw, $loss, $bye, $forfeitWin, $forfeitLoss);
    }

    public function testADrawGivesNoMoreThanAWin(): void
    {
        $this->expectException(InvalidTournament::class);
        $this->expectExceptionMessageIs('A win gives at least the points of a draw, and a draw at least those of a loss.');
        new Scoring(1, 2, 0);
    }

    public function testALossGivesNoMoreThanADraw(): void
    {
        $this->expectException(InvalidTournament::class);
        new Scoring(3, 1, 2);
    }
}
