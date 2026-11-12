<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Leaderboards\Aggregate;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Chance;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Direction;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\LeaderboardProjection;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Period\PeriodKind;
use Tilttek\Leaderboards\Period\Schedule;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\InMemoryLeagueStore;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\Leaderboards\Tests\Unit\Fixture\Kit;
use Tilttek\Leaderboards\TieBreak;
use Tilttek\Leaderboards\Title;

/**
 * The edges of the values the leaderboards accept, and the paths a happy case never takes.
 */
#[CoversClass(Board::class)]
#[CoversClass(Boards::class)]
#[CoversClass(Chance::class)]
#[CoversClass(LeaderboardProjection::class)]
#[CoversClass(Measure::class)]
#[CoversClass(Membership::class)]
#[CoversClass(Ranking::class)]
#[CoversClass(Schedule::class)]
#[CoversClass(TieBreak::class)]
#[CoversClass(Title::class)]
#[CoversClass(InMemoryScoreStore::class)]
#[CoversClass(InMemoryLeagueStore::class)]
final class BoundariesTest extends TestCase
{
    public function testTheDaysOfAWindowAreCheckedAtBothEnds(): void
    {
        self::assertSame(366, new Board('a', Measure::Count, new Schedule(PeriodKind::Window), ['x.y'], window: 366)->window);
        self::assertSame(1, new Board('a', Measure::Count, new Schedule(PeriodKind::Window), ['x.y'], window: 1)->window);
    }

    /**
     * @return iterable<string, array{PeriodKind, int}>
     */
    public static function refusedWindows(): iterable
    {
        yield 'a window of no day' => [PeriodKind::Window, 0];
        yield 'a window of a day too many' => [PeriodKind::Window, 367];
        yield 'days before the first' => [PeriodKind::Ever, -1];
        yield 'days on another schedule' => [PeriodKind::Month, 1];
    }

    #[DataProvider('refusedWindows')]
    public function testAWindowThatIsNotOneIsRefused(PeriodKind $kind, int $window): void
    {
        $this->expectException(InvalidLeaderboard::class);

        new Board('a', Measure::Count, new Schedule($kind), ['x.y'], window: $window);
    }

    public function testTheTieBreaksOfABoardAreReportedWhenItsMeasureIs(): void
    {
        $count = new TieBreak(Aggregate::Count);
        $reported = new TieBreak(Aggregate::Reported);

        self::assertSame([$count], new Board('a', Measure::Count, new Schedule(PeriodKind::Ever), ['x.y'], tieBreaks: [$count])->tieBreaks);
        self::assertSame([$reported], new Board('a', Measure::Highest, new Schedule(PeriodKind::Ever), tieBreaks: [$reported])->tieBreaks);

        foreach ([[Measure::Count, ['x.y'], $reported], [Measure::Highest, [], $count]] as [$measure, $verbs, $tieBreak]) {
            try {
                new Board('a', $measure, new Schedule(PeriodKind::Ever), $verbs, tieBreaks: [$tieBreak]);
                self::fail('The tie-breaks of a board are reported when its measure is, and only then.');
            } catch (InvalidLeaderboard $refused) {
                self::assertStringContainsString('The tie-breaks of the board "a" are reported when its measure is, and only then.', $refused->getMessage());
            }
        }
    }

    public function testABoardKeepsTheDirectionsOfItsTieBreaks(): void
    {
        $board = new Board('a', Measure::Total, new Schedule(PeriodKind::Ever), ['x.y'], tieBreaks: [new TieBreak(Aggregate::Count, Direction::Ascending), new TieBreak(Aggregate::Total)]);

        self::assertSame([Direction::Ascending, Direction::Descending], $board->ranking->tieBreaks);
    }

    public function testAnActivityTheTieBreaksCannotReadDoesNotCount(): void
    {
        $board = new Board('a', Measure::Total, new Schedule(PeriodKind::Ever), ['x.y'], tieBreaks: [new TieBreak(Aggregate::Lowest, Direction::Ascending, 'seconds')]);

        self::assertTrue($board->matches($this->activity('k1', '2026-11-03 10:00', context: ['seconds' => 0])));
        self::assertFalse($board->matches($this->activity('k1', '2026-11-03 10:00')));
    }

    public function testAContributionAndAScoreGatherTheTieBreaks(): void
    {
        $board = new Board('a', Measure::Total, new Schedule(PeriodKind::Ever), ['x.y'], tieBreaks: [new TieBreak(Aggregate::Count), new TieBreak(Aggregate::Highest, context: 'seconds')]);
        $period = Period::ever();
        $first = $board->contribution($this->activity('k1', '2026-11-03 10:00', 5, ['seconds' => 40]), $period);
        $second = $board->contribution($this->activity('k2', '2026-11-04 10:00', 2, ['seconds' => 70]), $period);

        self::assertSame([1, 40], $first->tieBreaks);

        $score = $board->add(null, $first);
        self::assertInstanceOf(Score::class, $score);
        self::assertSame([1, 40], $score->tieBreaks);

        $added = $board->add($score, $second);
        self::assertInstanceOf(Score::class, $added);
        self::assertSame([2, 70], $added->tieBreaks);
        self::assertSame([2, 70], $board->recount([$second, $first])->tieBreaks);
    }

    public function testARecountIsOfTheMemberOfItsFirstContribution(): void
    {
        $board = new Board('a', Measure::Total, new Schedule(PeriodKind::Ever), ['x.y']);
        $first = new Contribution('a', 'ever', ResourceReference::fromString('member:1'), 'k1', 2, new DateTimeImmutable('2026-11-03 10:00 UTC'));
        $other = new Contribution('a', 'ever', ResourceReference::fromString('member:2'), 'k2', 3, new DateTimeImmutable('2026-11-04 10:00 UTC'));

        self::assertSame('member:1', $board->recount([$first, $other])->member->toString());
        self::assertSame('member:2', $board->recount([$other, $first])->member->toString());
        self::assertSame('member:1', $board->recount([$first])->member->toString());
    }

    public function testBoardsFromArrayReadEveryOption(): void
    {
        $boards = Boards::fromArray([
            'full' => ['measure' => 'total', 'verbs' => ['x.y'], 'tie_breaks' => [['measure' => 'count', 'order' => 'asc']], 'per_object' => true, 'title' => ['name' => 'top', 'threshold' => 3, 'leader' => true], 'chance' => ['dice' => 3, 'faces' => 20]],
            'plain' => ['measure' => 'days', 'verbs' => ['x.y'], 'period' => 'window', 'window' => 9, 'title' => ['name' => 'regular', 'threshold' => 4], 'chance' => []],
            'bare' => ['measure' => 'total', 'verbs' => ['x.y']],
            'dice' => ['measure' => 'total', 'verbs' => ['x.y'], 'chance' => ['dice' => 4]],
            'faces' => ['measure' => 'total', 'verbs' => ['x.y'], 'chance' => ['faces' => 12]],
        ]);

        $full = $boards->get('full');
        self::assertEquals([new TieBreak(Aggregate::Count, Direction::Ascending)], $full->tieBreaks);
        self::assertTrue($full->perObject);
        self::assertSame(0, $full->window);
        self::assertEquals(new Title('top', 3, true), $full->title);
        self::assertEquals(new Chance(3, 20), $full->chance);

        $plain = $boards->get('plain');
        self::assertSame([[], 9, false], [$plain->tieBreaks, $plain->window, $plain->perObject]);
        self::assertEquals(new Title('regular', 4), $plain->title);
        self::assertEquals(new Chance(1, 6), $plain->chance);

        $bare = $boards->get('bare');
        self::assertSame([[], 0, false, null, null], [$bare->tieBreaks, $bare->window, $bare->perObject, $bare->title, $bare->chance]);
        self::assertEquals(new Chance(4, 6), $boards->get('dice')->chance);
        self::assertEquals(new Chance(1, 12), $boards->get('faces')->chance);
    }

    public function testTheTieBreaksOfABoardFromArrayAreChecked(): void
    {
        foreach ([[['measure' => 'median'], 'gathers a count, a total, the highest, the lowest or a reported value; "median" is none of them'], [['measure' => 'count', 'order' => 'up'], 'orders "desc" or "asc"; "up" is neither']] as [$tieBreak, $message]) {
            try {
                Boards::fromArray(['a' => ['measure' => 'total', 'verbs' => ['x.y'], 'tie_breaks' => [$tieBreak]]]);
                self::fail('A tie-break that is none is refused.');
            } catch (InvalidLeaderboard $refused) {
                self::assertStringContainsString($message, $refused->getMessage());
            }
        }
    }

    public function testAContributionWithoutTieBreaksCountsZero(): void
    {
        $board = new Board('a', Measure::Total, new Schedule(PeriodKind::Ever), ['x.y'], tieBreaks: [new TieBreak(Aggregate::Total)]);
        $member = ResourceReference::fromString('member:1');
        $bare = new Contribution('a', 'ever', $member, 'k1', 2, new DateTimeImmutable('2026-11-03 10:00 UTC'));
        $score = $board->add(null, $bare);

        self::assertInstanceOf(Score::class, $score);
        self::assertSame([0], $score->tieBreaks);
        self::assertSame([0], $board->recount([$bare])->tieBreaks);
        self::assertSame([0], $board->recount([$bare, new Contribution('a', 'ever', $member, 'k2', 2, new DateTimeImmutable('2026-11-04 10:00 UTC'))])->tieBreaks);
    }

    public function testADiceRollHasItsEdges(): void
    {
        self::assertSame([10, 100], [new Chance(10, 100)->dice, new Chance(10, 100)->faces]);
        self::assertSame([1, 2], [new Chance(1, 2)->dice, new Chance(1, 2)->faces]);
    }

    public function testATitleMayBeHeldFromTheHighestScore(): void
    {
        self::assertSame(Score::MAX, new Title('a', Score::MAX)->threshold);
    }

    public function testATieBreakReadsAZero(): void
    {
        self::assertTrue(new TieBreak(Aggregate::Total, context: 'seconds')->reads($this->activity('k1', '2026-11-03 10:00', context: ['seconds' => 0])));
    }

    public function testACohortGoesUpToSixtyFour(): void
    {
        $joined = new DateTimeImmutable('2026-11-03 UTC');
        $member = ResourceReference::fromString('member:1');

        self::assertSame(64, new Membership('a', 'w', $member, 'bronze', 1, $joined, cohort: 64)->cohort);

        $this->expectException(InvalidLeaderboard::class);
        new Membership('a', 'w', $member, 'bronze', 1, $joined, cohort: 65);
    }

    public function testTheCohortsDoubleWithTheScore(): void
    {
        $league = new League(['a', 'b']);

        self::assertSame([0, 1, 2, 2, 3, 3, 4], array_map($league->cohortOf(...), [0, 1, 2, 3, 4, 7, 8]));
    }

    public function testTheBestKeepsTheScoreItAlreadyHasWhenAnotherIsReachedAtTheSameMoment(): void
    {
        $board = new Board('a', Measure::Best, new Schedule(PeriodKind::Ever), ['x.y']);
        $member = ResourceReference::fromString('member:1');
        $score = new Score('a', 'ever', $member, 5, new DateTimeImmutable('2026-11-03 10:00 UTC'));
        $same = new Contribution('a', 'ever', $member, 'k1', 5, new DateTimeImmutable('2026-11-03 05:00 America/Toronto'));

        $kept = $board->add($score, $same);

        self::assertInstanceOf(Score::class, $kept);
        self::assertSame($score->reachedAt, $kept->reachedAt);
    }

    public function testASlidingWindowAndItsPeriod(): void
    {
        $schedule = new Schedule(PeriodKind::Window);
        $moment = new DateTimeImmutable('2026-11-07 12:00 UTC');

        self::assertEquals(Period::window(), $schedule->at($moment));
        self::assertNull($schedule->previous($moment));
        self::assertNull(new Schedule(PeriodKind::Ever)->previous($moment));
    }

    public function testTheKeyOfAScoreSortsByValueTieBreaksAndMoment(): void
    {
        $ranking = new Ranking([Direction::Descending, Direction::Ascending]);
        $score = new Score('a', 'ever', ResourceReference::fromString('member:1'), 7, new DateTimeImmutable('2026-11-07 12:00:00.250000 UTC'), [4, 3]);

        self::assertSame('099999999999999309999999999999960000000000000003', $ranking->valuesKey($score));
        self::assertSame('099999999999999309999999999999960000000000000003063929649600250000member:1', $ranking->key($score));
    }

    public function testAMissingTieBreakIsAZero(): void
    {
        $with = new Score('a', 'ever', ResourceReference::fromString('member:1'), 7, new DateTimeImmutable('2026-11-07 12:00 UTC'), [0, 0]);
        $without = new Score('a', 'ever', ResourceReference::fromString('member:1'), 7, new DateTimeImmutable('2026-11-07 12:00 UTC'));

        foreach ([Direction::Descending, Direction::Ascending] as $direction) {
            $ranking = new Ranking([$direction, $direction]);

            self::assertSame(0, $ranking->compareValues($with, $without));
            self::assertSame(0, $ranking->compareValues($without, $with));
            self::assertSame($ranking->valuesKey($with), $ranking->valuesKey($without));
        }
    }

    public function testAScoreOnTheFirstMomentOfAWindowCounts(): void
    {
        $kit = new Kit(Boards::fromArray(['w' => ['measure' => 'count', 'verbs' => ['lesson.done'], 'period' => 'window', 'window' => 7]]));
        $kit->record('k0', 'member:1', 'lesson.done', '2026-10-31 23:59:59');
        $kit->record('k1', 'member:1', 'lesson.done', '2026-11-01 00:00:00');

        self::assertSame(1, $kit->scores->score('w', Period::WINDOW, ResourceReference::fromString('member:1'))?->value);
    }

    public function testAScoreIsAddedToTheOneTheStoreHolds(): void
    {
        $kit = new Kit(Boards::fromArray(['c' => ['measure' => 'count', 'verbs' => ['lesson.done']]]));
        $member = ResourceReference::fromString('member:1');
        $kit->scores->save(new Score('c', Period::EVER, $member, 10, new DateTimeImmutable('2026-11-01 UTC')));
        $kit->record('k1', 'member:1', 'lesson.done', '2026-11-02 10:00');

        self::assertSame(11, $kit->scores->score('c', Period::EVER, $member)?->value);
    }

    public function testAnEarlierDayCountedAgainKeepsEachContributionOnce(): void
    {
        $kit = new Kit(Boards::fromArray(['streak' => ['measure' => 'days', 'verbs' => ['lesson.done'], 'tie_breaks' => [['measure' => 'total']]]]));
        $kit->record('k1', 'member:1', 'lesson.done', '2026-11-05 10:00', 5);
        $kit->record('k2', 'member:1', 'lesson.done', '2026-11-03 10:00', 3);

        $score = $kit->scores->score('streak', Period::EVER, ResourceReference::fromString('member:1'));

        self::assertSame([2, [8]], [$score?->value, $score?->tieBreaks]);
    }

    public function testAScoreOnAnObjectSurvivesACancellation(): void
    {
        $kit = new Kit(Boards::fromArray(['records' => ['measure' => 'best', 'verbs' => ['game.scored'], 'per_object' => true]]));
        $kit->recordOn('k1', 'member:1', 'game.scored', '2026-11-03 10:00', 'game:catan', 5);
        $kit->recordOn('k2', 'member:1', 'game.scored', '2026-11-04 10:00', 'game:catan', 8);
        $kit->journal->cancel('k2');

        $board = $kit->boards->get('records');
        $score = $kit->scores->score($board->key(ResourceReference::fromString('game:catan')), Period::EVER, ResourceReference::fromString('member:1'));

        self::assertSame(5, $score?->value);
    }

    public function testTheContributionsThatLeaveAreAList(): void
    {
        $store = new InMemoryScoreStore();
        $member = ResourceReference::fromString('member:1');

        foreach ([['k1', '2026-11-03 10:00'], ['k2', '2026-11-04 10:00'], ['k3', '2026-11-05 10:00'], ['k4', '2026-11-06 10:00']] as [$activity, $at]) {
            $store->add(new Contribution('a', Period::WINDOW, $member, $activity, 1, new DateTimeImmutable($at.' UTC')));
        }

        $store->add(new Contribution('a@0123', Period::WINDOW, $member, 'k5', 1, new DateTimeImmutable('2026-11-03 10:00 UTC')));
        $expired = $store->expire('a', new DateTimeImmutable('2026-11-06 00:00 UTC'));

        self::assertSame(['k1', 'k2', 'k3', 'k5'], array_map(static fn (Contribution $contribution): string => $contribution->activity, $expired));
        self::assertSame([0, 1, 2, 3], array_keys($expired));
        self::assertCount(1, $store->contributions('a', Period::WINDOW, $member));
    }

    public function testADivisionIsFilledInTheOrderTheyAreNumbered(): void
    {
        $store = new InMemoryLeagueStore();
        $at = new DateTimeImmutable('2026-11-03 UTC');

        $divisions = [];

        foreach ([1 => 1, 2 => 2, 3 => 1, 4 => 2, 5 => 1] as $number => $cohort) {
            $divisions[] = $store->join('a', 'w', ResourceReference::fromString('member:'.$number), 'bronze', $at, 2, $cohort)->division;
        }

        self::assertSame([1, 2, 1, 2, 3], $divisions);
    }

    /**
     * @param array<string, bool|int|string> $context
     */
    private function activity(string $key, string $at, int $quantity = 1, array $context = []): Activity
    {
        return Activity::of($key, 'member:1', 'x.y', new DateTimeImmutable($at.' UTC'), null, $quantity, $context);
    }
}
