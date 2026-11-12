<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Exception\UnknownBoard;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Names;
use Tilttek\Leaderboards\Period\PeriodKind;
use Tilttek\Leaderboards\Period\Schedule;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Ties;

#[CoversClass(Board::class)]
#[CoversClass(Boards::class)]
#[CoversClass(Measure::class)]
#[CoversClass(UnknownBoard::class)]
#[CoversClass(Names::class)]
final class BoardTest extends TestCase
{
    public function testABoard(): void
    {
        $board = new Board('games', Measure::Count, new Schedule(PeriodKind::Month), ['game.played', 'game.won'], ['ranked' => true]);

        self::assertSame('games', $board->name);
        self::assertSame(Ties::FirstReached, $board->ties);
        self::assertSame(100, $board->snapshotSize);
        self::assertTrue($board->matches($this->activity('game.played', ['ranked' => true])));
        self::assertTrue($board->matches($this->activity('game.won', ['ranked' => true, 'system' => 'pf2'])));
        self::assertFalse($board->matches($this->activity('game.played')));
        self::assertFalse($board->matches($this->activity('game.played', ['ranked' => 1])));
        self::assertFalse($board->matches($this->activity('game.lost', ['ranked' => true])));
        self::assertFalse(new Board('level', Measure::Latest, new Schedule(PeriodKind::Ever))->matches($this->activity('game.played')));
        self::assertSame(1, new Board('a', Measure::Count, new Schedule(PeriodKind::Ever), ['game.played'], snapshotSize: 1)->snapshotSize);
        self::assertSame(1000, new Board('a', Measure::Count, new Schedule(PeriodKind::Ever), ['game.played'], snapshotSize: 1000)->snapshotSize);
    }

    /**
     * @return iterable<string, array{string, Measure, list<string>, array<string, bool|int|string>, int, string}>
     */
    public static function refusedBoards(): iterable
    {
        yield 'a name ending in a line break' => ["games\n", Measure::Count, ['game.played'], [], 100, 'is not a board name'];
        yield 'a name with a space' => ['a b', Measure::Count, ['game.played'], [], 100, '"a b" is not a board name'];
        yield 'a journal measure without verbs' => ['a', Measure::Total, [], [], 100, 'The board "a" reads verbs when its measure comes from the journal, and only then.'];
        yield 'a reported measure with verbs' => ['a', Measure::Highest, ['game.played'], [], 100, 'The board "a" reads verbs when its measure comes from the journal, and only then.'];
        yield 'a reported measure with a context' => ['a', Measure::Latest, [], ['ranked' => true], 100, 'The board "a" has a reported measure: it reads no context.'];
        yield 'an empty snapshot' => ['a', Measure::Count, ['game.played'], [], 0, 'A snapshot keeps 1 to 1000 members; the board "a" asks for 0.'];
        yield 'a snapshot too large' => ['a', Measure::Count, ['game.played'], [], 1001, 'A snapshot keeps 1 to 1000 members; the board "a" asks for 1001.'];
    }

    /**
     * @param list<string>                   $verbs
     * @param array<string, bool|int|string> $context
     */
    #[DataProvider('refusedBoards')]
    public function testABoardIsRefused(string $name, Measure $measure, array $verbs, array $context, int $size, string $message): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Board($name, $measure, new Schedule(PeriodKind::Ever), $verbs, $context, snapshotSize: $size);
    }

    public function testTheMeasures(): void
    {
        $activity = $this->activity('game.played', quantity: 7);

        self::assertSame([true, true, true, true, false, false], array_map(static fn (Measure $measure): bool => $measure->readsJournal(), Measure::cases()));
        self::assertSame([1, 7, 7, 1, 7, 7], array_map(static fn (Measure $measure): int => $measure->amountFor($activity), Measure::cases()));
    }

    public function testATotalAddsUpAndIsReachedAtTheLatestGesture(): void
    {
        $total = $this->board(Measure::Total);
        $first = $total->add(null, $this->contribution(5, '2026-11-03 10:00'));
        $this->assertInstanceOf(Score::class, $first);
        $second = $total->add($first, $this->contribution(3, '2026-11-02 10:00'));
        $this->assertInstanceOf(Score::class, $second);
        $third = $this->board(Measure::Count)->add($second, $this->contribution(1, '2026-11-04 10:00'));

        self::assertEquals($this->score(5, '2026-11-03 10:00'), $first);
        self::assertEquals($this->score(8, '2026-11-03 10:00'), $second);
        self::assertEquals($this->score(9, '2026-11-04 10:00'), $third);
        self::assertEquals($this->score(9, '2026-11-04 10:00'), $total->recount([$this->contribution(1, '2026-11-04 10:00'), $this->contribution(5, '2026-11-03 10:00'), $this->contribution(3, '2026-11-02 10:00')]));
        self::assertEquals($this->score(Score::MAX, '2026-11-04 10:00'), $total->add($this->score(Score::MAX - 1, '2026-11-03 10:00'), $this->contribution(5, '2026-11-04 10:00')));
    }

    public function testTheBestKeepsTheHighestAmountReachedFirst(): void
    {
        $score = $this->score(5, '2026-11-03 10:00');
        $best = $this->board(Measure::Best);

        self::assertEquals($score, $best->add($score, $this->contribution(4, '2026-11-01 10:00')));
        self::assertEquals($score, $best->add($score, $this->contribution(5, '2026-11-03 10:00')));
        self::assertEquals($score, $best->add($score, $this->contribution(5, '2026-11-04 10:00')));
        self::assertEquals($this->score(5, '2026-11-02 10:00'), $best->add($score, $this->contribution(5, '2026-11-02 10:00')));
        self::assertEquals($this->score(6, '2026-11-05 10:00'), $best->add($score, $this->contribution(6, '2026-11-05 10:00')));
        self::assertEquals($this->score(6, '2026-11-02 10:00'), $best->recount([$this->contribution(2, '2026-11-01 10:00'), $this->contribution(6, '2026-11-05 10:00'), $this->contribution(6, '2026-11-02 10:00')]));
    }

    public function testBoardsFromArray(): void
    {
        $boards = Boards::fromArray([
            'weekly' => ['measure' => 'best', 'verbs' => ['arcade.scored'], 'context' => ['level' => 3], 'period' => 'week', 'timezone' => 'Europe/Paris', 'ties' => 'shared', 'snapshot_size' => 10],
            'games' => ['measure' => 'count', 'verbs' => ['game.played', 'game.*']],
            'summer' => ['measure' => 'total', 'verbs' => ['game.*', 'quest.done'], 'period' => 'season', 'seasons' => [['code' => 'summer-2026', 'start' => '2026-06-21', 'end' => '2026-09-22']]],
            'level' => ['measure' => 'latest'],
        ], 'America/Toronto');

        self::assertSame(['games', 'level', 'summer', 'weekly'], array_map(static fn (Board $board): string => $board->name, $boards->all()));
        self::assertSame(['game.played', 'game.*', 'quest.done', 'arcade.scored'], $boards->patterns());
        self::assertTrue($boards->has('games'));
        self::assertFalse($boards->has('nothing'));

        $weekly = $boards->get('weekly');
        self::assertSame([Measure::Best, PeriodKind::Week, 'Europe/Paris', Ties::Shared, 10, ['level' => 3]], [$weekly->measure, $weekly->schedule->kind, $weekly->schedule->timezone->getName(), $weekly->ties, $weekly->snapshotSize, $weekly->conditions->values]);

        $games = $boards->get('games');
        self::assertSame([Measure::Count, PeriodKind::Ever, 'America/Toronto', Ties::FirstReached, 100, []], [$games->measure, $games->schedule->kind, $games->schedule->timezone->getName(), $games->ties, $games->snapshotSize, $games->conditions->values]);

        $summer = $boards->get('summer')->schedule->seasons['summer-2026'];
        self::assertSame(['2026-06-21 00:00 -04:00', '2026-09-22 00:00 -04:00'], [$summer->start?->format('Y-m-d H:i P'), $summer->end?->format('Y-m-d H:i P')]);
        self::assertSame('UTC', Boards::fromArray(['level' => ['measure' => 'latest']])->get('level')->schedule->timezone->getName());
    }

    /**
     * @return iterable<string, array{array<string, array{measure: string, verbs?: list<string>, period?: string, timezone?: string, seasons?: list<array{code: string, start: string, end: string}>, ties?: string}>, string}>
     */
    public static function refusedArrays(): iterable
    {
        yield 'an unknown measure' => [['a' => ['measure' => 'average']], 'The measure of the board "a" is count, total, best, days, latest or highest; "average" is none of them.'];
        yield 'an unknown period' => [['a' => ['measure' => 'latest', 'period' => 'decade']], 'The period of the board "a" is day, week, month, quarter, year, season, ever or window; "decade" is none of them.'];
        yield 'unknown ties' => [['a' => ['measure' => 'latest', 'ties' => 'last']], 'The ties of the board "a" go to the "first" to reach the score, or are "shared".'];
        yield 'an unknown timezone' => [['a' => ['measure' => 'latest', 'timezone' => 'Mars/Olympus']], '"Mars/Olympus" is not a timezone.'];
        yield 'a date that is not one' => [['a' => ['measure' => 'latest', 'period' => 'season', 'seasons' => [['code' => 's', 'start' => 'soon', 'end' => '2026-09-22']]]], '"soon" is not a date.'];
    }

    /**
     * @param array<string, array{measure: string, verbs?: list<string>, period?: string, timezone?: string, seasons?: list<array{code: string, start: string, end: string}>, ties?: string}> $boards
     */
    #[DataProvider('refusedArrays')]
    public function testBoardsFromArrayAreRefused(array $boards, string $message): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains($message);

        Boards::fromArray($boards);
    }

    public function testTwoBoardsByOneNameAreRefused(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('Two boards are named "a".');

        new Boards(new Board('a', Measure::Latest, new Schedule(PeriodKind::Ever)), new Board('a', Measure::Highest, new Schedule(PeriodKind::Ever)));
    }

    public function testAnUnknownBoard(): void
    {
        $this->expectException(UnknownBoard::class);
        $this->expectExceptionMessageIsOrContains('No board is named "nothing".');

        new Boards()->get('nothing');
    }

    public function testTheTimezoneOfABoardIsReadForItsSeasons(): void
    {
        $boards = Boards::fromArray(['a' => ['measure' => 'latest', 'period' => 'season', 'timezone' => 'Asia/Tokyo', 'seasons' => [['code' => 's', 'start' => '2026-06-21', 'end' => '2026-09-22']]]], 'America/Toronto');

        self::assertSame('2026-06-20 15:00', $boards->get('a')->schedule->seasons['s']->start?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'));
    }

    /**
     * @param array<string, bool|int|string> $context
     */
    private function activity(string $verb, array $context = [], int $quantity = 1): Activity
    {
        return Activity::of('k1', 'member:1', $verb, new DateTimeImmutable('2026-11-07 UTC'), quantity: $quantity, context: $context);
    }

    private function board(Measure $measure): Board
    {
        return new Board('games', $measure, new Schedule(PeriodKind::Month), ['game.played']);
    }

    private function contribution(int $amount, string $at): Contribution
    {
        return new Contribution('games', '2026-11', ResourceReference::fromString('member:1'), 'k'.$amount.$at, $amount, new DateTimeImmutable($at.' UTC'));
    }

    private function score(int $value, string $at): Score
    {
        return new Score('games', '2026-11', ResourceReference::fromString('member:1'), $value, new DateTimeImmutable($at.' UTC'));
    }
}
