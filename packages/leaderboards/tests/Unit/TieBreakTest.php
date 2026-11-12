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
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Direction;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Period\PeriodKind;
use Tilttek\Leaderboards\Period\Schedule;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Row;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Tests\Unit\Fixture\Kit;
use Tilttek\Leaderboards\TieBreak;
use Tilttek\Leaderboards\Ties;

#[CoversClass(Aggregate::class)]
#[CoversClass(Direction::class)]
#[CoversClass(TieBreak::class)]
#[CoversClass(Ranking::class)]
final class TieBreakTest extends TestCase
{
    public function testTheDirections(): void
    {
        self::assertSame([-1, 0, 1], [Direction::Descending->compare(5, 3), Direction::Descending->compare(3, 3), Direction::Descending->compare(3, 5)]);
        self::assertSame([1, 0, -1], [Direction::Ascending->compare(5, 3), Direction::Ascending->compare(3, 3), Direction::Ascending->compare(3, 5)]);
    }

    public function testTheAggregates(): void
    {
        self::assertSame([4, 4, 4, 4, 4], array_map(static fn (Aggregate $aggregate): int => $aggregate->gather(null, 4), Aggregate::cases()));
        self::assertSame([7, 7, 4, 3, 4], array_map(static fn (Aggregate $aggregate): int => $aggregate->gather(3, 4), Aggregate::cases()));
        self::assertSame(Score::MAX, Aggregate::Total->gather(Score::MAX, 1));
        self::assertSame(Score::MAX, Aggregate::Count->gather(Score::MAX - 1, 1));
    }

    public function testWhatATieBreakReads(): void
    {
        $activity = Activity::of('k1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-03 UTC'), quantity: 5, context: ['seconds' => 42, 'name' => 'x', 'negative' => -1, 'huge' => Score::MAX + 1, 'most' => Score::MAX]);

        self::assertSame(1, new TieBreak(Aggregate::Count)->amountFor($activity));
        self::assertSame(5, new TieBreak(Aggregate::Total)->amountFor($activity));
        self::assertSame(42, new TieBreak(Aggregate::Lowest, Direction::Ascending, 'seconds')->amountFor($activity));
        self::assertSame(0, new TieBreak(Aggregate::Lowest, Direction::Ascending, 'name')->amountFor($activity));
        self::assertTrue(new TieBreak(Aggregate::Total)->reads($activity));
        self::assertTrue(new TieBreak(Aggregate::Total, context: 'seconds')->reads($activity));
        self::assertTrue(new TieBreak(Aggregate::Total, context: 'most')->reads($activity));
        self::assertFalse(new TieBreak(Aggregate::Total, context: 'name')->reads($activity));
        self::assertFalse(new TieBreak(Aggregate::Total, context: 'negative')->reads($activity));
        self::assertFalse(new TieBreak(Aggregate::Total, context: 'huge')->reads($activity));
        self::assertFalse(new TieBreak(Aggregate::Total, context: 'missing')->reads($activity));
        self::assertSame(Direction::Descending, new TieBreak(Aggregate::Total)->direction);
    }

    /**
     * @return iterable<string, array{Aggregate, string}>
     */
    public static function refusedContexts(): iterable
    {
        yield 'a count' => [Aggregate::Count, 'A tie-break that reads the context value "seconds" adds it up, keeps the highest or the lowest; it does not count nor take a reported value.'];
        yield 'a reported value' => [Aggregate::Reported, 'A tie-break that reads the context value "seconds" adds it up, keeps the highest or the lowest; it does not count nor take a reported value.'];
    }

    #[DataProvider('refusedContexts')]
    public function testATieBreakIsRefused(Aggregate $aggregate, string $message): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains($message);

        new TieBreak($aggregate, context: 'seconds');
    }

    public function testAContextNameIsChecked(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('"Seconds" is not a context name');

        new TieBreak(Aggregate::Total, context: 'Seconds');
    }

    public function testARankingOrdersByTheScoreTheTieBreaksThenTheArrival(): void
    {
        $ranking = new Ranking([Direction::Ascending, Direction::Descending]);
        $a = $this->score('member:1', 7, '2026-11-01', [3, 10]);
        $b = $this->score('member:2', 7, '2026-11-05', [2, 1]);
        $c = $this->score('member:3', 7, '2026-11-01', [3, 10]);
        $d = $this->score('member:4', 8, '2026-11-09', [9, 0]);
        $e = $this->score('member:5', 7, '2026-11-01', [3, 11]);

        self::assertTrue($ranking->byArrival());
        self::assertSame([-1, 1, -1, 1, 1, -1, 0], [$ranking->compare($d, $a), $ranking->compare($a, $b), $ranking->compare($a, $c), $ranking->compare($c, $a), $ranking->compare($a, $e), $ranking->compare($e, $a), $ranking->compare($a, $a)]);
        self::assertSame([0, 1, -1], [$ranking->compareValues($a, $c), $ranking->compareValues($a, $b), $ranking->compareValues($e, $a)]);
        self::assertTrue($ranking->ranksBefore($a, $c));
        self::assertFalse($ranking->ranksBefore($c, $a));
        self::assertFalse($ranking->ranksBefore($a, $a));

        $shared = new Ranking([Direction::Ascending, Direction::Descending], Ties::Shared);
        self::assertFalse($shared->byArrival());
        self::assertFalse($shared->ranksBefore($a, $c));
        self::assertTrue($shared->ranksBefore($b, $c));
        self::assertSame(
            [[1, 'member:4'], [2, 'member:2'], [3, 'member:5'], [4, 'member:1'], [4, 'member:3']],
            array_map(static fn (array $ranked): array => [$ranked['rank'], $ranked['score']->member->toString()], $shared->rank([$d, $b, $e, $a, $c])),
        );
        self::assertSame(
            [[1, 'member:4'], [2, 'member:2'], [3, 'member:5'], [4, 'member:1'], [5, 'member:3']],
            array_map(static fn (array $ranked): array => [$ranked['rank'], $ranked['score']->member->toString()], $ranking->rank([$d, $b, $e, $a, $c])),
        );
        self::assertSame([[1, 'member:1'], [1, 'member:3']], array_map(static fn (array $ranked): array => [$ranked['rank'], $ranked['score']->member->toString()], $shared->rank([$a, $c])));
        self::assertSame([], $shared->rank([]));
    }

    public function testThreeTieBreaksAreRefused(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('A board orders a tie by 2 measures at most; 3 were given.');

        new Ranking([Direction::Ascending, Direction::Ascending, Direction::Ascending]);
    }

    public function testAScoreHoldsTwoTieBreaksAtMost(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('A score holds 2 tie-breaks at most; 3 were given.');

        $this->score('member:1', 1, '2026-11-01', [1, 2, 3]);
    }

    public function testATieBreakIsAScore(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('A score and its tie-breaks are 0 to 1000000000000000; -1 is not one.');

        $this->score('member:1', 1, '2026-11-01', [0, -1]);
    }

    public function testABoardGathersItsTieBreaks(): void
    {
        $board = new Board('arcade', Measure::Best, new Schedule(PeriodKind::Month), ['arcade.scored'], tieBreaks: [new TieBreak(Aggregate::Count, Direction::Ascending), new TieBreak(Aggregate::Lowest, Direction::Ascending, 'seconds')]);
        $period = new Period('2026-11');
        $first = $board->contribution(Activity::of('k1', 'member:1', 'arcade.scored', new DateTimeImmutable('2026-11-03 10:00 UTC'), quantity: 50, context: ['seconds' => 90]), $period);
        $second = $board->contribution(Activity::of('k2', 'member:1', 'arcade.scored', new DateTimeImmutable('2026-11-04 10:00 UTC'), quantity: 40, context: ['seconds' => 60]), $period);

        self::assertEquals(new Contribution('arcade', '2026-11', ResourceReference::fromString('member:1'), 'k1', 50, new DateTimeImmutable('2026-11-03 10:00 UTC'), [1, 90]), $first);
        self::assertEquals([Direction::Ascending, Direction::Ascending], $board->ranking->tieBreaks);
        self::assertSame(Ties::FirstReached, $board->ranking->ties);

        $score = $board->add($board->add(null, $first), $second);
        self::assertEquals($this->score('member:1', 50, '2026-11-03 10:00', [2, 60], 'arcade'), $score);
        self::assertEquals($score, $board->recount([$second, $first]));
        self::assertFalse($board->matches(Activity::of('k3', 'member:1', 'arcade.scored', new DateTimeImmutable('2026-11-04 UTC'))));
        self::assertTrue($board->matches(Activity::of('k3', 'member:1', 'arcade.scored', new DateTimeImmutable('2026-11-04 UTC'), context: ['seconds' => 1])));
    }

    /**
     * @return iterable<string, array{Measure, list<string>, list<TieBreak>, string}>
     */
    public static function refusedBoards(): iterable
    {
        yield 'a reported tie-break on the journal' => [Measure::Total, ['game.played'], [new TieBreak(Aggregate::Reported)], 'The tie-breaks of the board "a" are reported when its measure is, and only then.'];
        yield 'a gathered tie-break on a reported board' => [Measure::Latest, [], [new TieBreak(Aggregate::Total)], 'The tie-breaks of the board "a" are reported when its measure is, and only then.'];
        yield 'three tie-breaks' => [Measure::Latest, [], [new TieBreak(Aggregate::Reported), new TieBreak(Aggregate::Reported), new TieBreak(Aggregate::Reported)], 'A board orders a tie by 2 measures at most; 3 were given.'];
    }

    /**
     * @param list<string>   $verbs
     * @param list<TieBreak> $tieBreaks
     */
    #[DataProvider('refusedBoards')]
    public function testABoardWithTieBreaksIsRefused(Measure $measure, array $verbs, array $tieBreaks, string $message): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Board('a', $measure, new Schedule(PeriodKind::Ever), $verbs, tieBreaks: $tieBreaks);
    }

    public function testTieBreaksFromArray(): void
    {
        $board = Boards::fromArray(['a' => ['measure' => 'total', 'verbs' => ['game.played'], 'tie_breaks' => [['measure' => 'lowest', 'order' => 'asc', 'context' => 'seconds'], ['measure' => 'count']]]])->get('a');

        self::assertEquals([new TieBreak(Aggregate::Lowest, Direction::Ascending, 'seconds'), new TieBreak(Aggregate::Count)], $board->tieBreaks);
    }

    /**
     * @return iterable<string, array{array{measure: string, order?: string}, string}>
     */
    public static function refusedArrays(): iterable
    {
        yield 'an unknown measure' => [['measure' => 'median'], 'A tie-break of the board "a" gathers a count, a total, the highest, the lowest or a reported value; "median" is none of them.'];
        yield 'an unknown order' => [['measure' => 'count', 'order' => 'up'], 'A tie-break of the board "a" orders "desc" or "asc"; "up" is neither.'];
    }

    /**
     * @param array{measure: string, order?: string} $tieBreak
     */
    #[DataProvider('refusedArrays')]
    public function testTieBreaksFromArrayAreRefused(array $tieBreak, string $message): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains($message);

        Boards::fromArray(['a' => ['measure' => 'total', 'verbs' => ['game.played'], 'tie_breaks' => [$tieBreak]]]);
    }

    public function testAStandingBreaksTiesThenShares(): void
    {
        $kit = new Kit(Boards::fromArray([
            'arcade' => ['measure' => 'best', 'verbs' => ['arcade.scored'], 'period' => 'month', 'ties' => 'shared', 'tie_breaks' => [['measure' => 'count', 'order' => 'asc']]],
        ]));
        $kit->record('k1', 'member:1', 'arcade.scored', '2026-11-02 10:00', 50);
        $kit->record('k2', 'member:1', 'arcade.scored', '2026-11-02 11:00', 20);
        $kit->record('k3', 'member:2', 'arcade.scored', '2026-11-03 10:00', 50);
        $kit->record('k4', 'member:3', 'arcade.scored', '2026-11-04 10:00', 50);
        $kit->record('k5', 'member:4', 'arcade.scored', '2026-11-05 10:00', 10);

        $viewer = ResourceReference::fromString('member:1');

        $standing = $kit->leaderboards->standing('arcade', $viewer);

        self::assertSame([[1, 50], [1, 50], [3, 50], [4, 10]], array_map(static fn (Row $row): array => [$row->rank, $row->value], $standing->rows));
        self::assertSame(3, $standing->own?->rank);
        self::assertSame(1, $kit->leaderboards->rank('arcade', ResourceReference::fromString('member:3'))?->rank);
        self::assertSame([2], $kit->scores->score('arcade', '2026-11', $viewer)?->tieBreaks);

        $kit->journal->cancel('k2');

        self::assertSame(1, $kit->leaderboards->rank('arcade', $viewer)?->rank);
        self::assertSame([1], $kit->scores->score('arcade', '2026-11', $viewer)->tieBreaks);
    }

    public function testReportedTieBreaks(): void
    {
        $kit = new Kit(Boards::fromArray([
            'level' => ['measure' => 'highest', 'tie_breaks' => [['measure' => 'reported', 'order' => 'asc']]],
            'balance' => ['measure' => 'latest', 'tie_breaks' => [['measure' => 'reported']]],
        ]));
        $member = ResourceReference::fromString('member:1');
        $at = new DateTimeImmutable('2026-11-07 10:00 UTC');

        self::assertTrue($kit->leaderboards->report('level', $member, 5, $at, [30]));
        self::assertFalse($kit->leaderboards->report('level', $member, 5, $at, [40]));
        self::assertFalse($kit->leaderboards->report('level', $member, 5, $at, [30]));
        self::assertTrue($kit->leaderboards->report('level', $member, 5, $at, [20]));
        self::assertFalse($kit->leaderboards->report('level', $member, 4, $at, [1]));
        self::assertSame([20], $kit->scores->score('level', 'ever', $member)?->tieBreaks);

        self::assertTrue($kit->leaderboards->report('balance', $member, 5, $at, [1]));
        self::assertFalse($kit->leaderboards->report('balance', $member, 5, $at, [1]));
        self::assertTrue($kit->leaderboards->report('balance', $member, 5, $at, [2]));
        self::assertFalse($kit->leaderboards->report('balance', $member, 9, $at->modify('-1 second'), [2]));
        self::assertSame([2], $kit->scores->score('balance', 'ever', $member)?->tieBreaks);
    }

    public function testAReportGivesEveryTieBreak(): void
    {
        $kit = new Kit(Boards::fromArray(['level' => ['measure' => 'highest', 'tie_breaks' => [['measure' => 'reported']]]]));

        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The board "level" orders a tie by 1 values; 0 were reported.');

        $kit->leaderboards->report('level', ResourceReference::fromString('member:1'), 5);
    }

    /**
     * @param list<int> $tieBreaks
     */
    private function score(string $member, int $value, string $at, array $tieBreaks, string $board = 'games'): Score
    {
        return new Score($board, '2026-11', ResourceReference::fromString($member), $value, new DateTimeImmutable($at.' UTC'), $tieBreaks);
    }
}
