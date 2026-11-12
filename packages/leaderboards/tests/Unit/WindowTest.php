<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Filter;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\LeaderboardProjection;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Period\PeriodKind;
use Tilttek\Leaderboards\Period\Schedule;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\Leaderboards\Tests\Unit\Fixture\Kit;
use Tilttek\Leaderboards\Title;

#[CoversClass(Measure::class)]
#[CoversClass(Title::class)]
#[CoversClass(LeaderboardProjection::class)]
final class WindowTest extends TestCase
{
    public function testTheDaysCountEachDayOnceInTheBoardsTimezone(): void
    {
        $board = new Board('regular', Measure::Days, new Schedule(PeriodKind::Month, new DateTimeZone('America/Toronto')), ['lesson.done']);
        $first = $board->add(null, $this->contribution('2026-11-03 10:00'));

        self::assertEquals($this->score(1, '2026-11-03 10:00'), $first);
        $this->assertInstanceOf(Score::class, $first);
        self::assertNull($board->add($first, $this->contribution('2026-11-03 04:59')));
        self::assertEquals($this->score(1, '2026-11-03 05:00'), $board->add($first, $this->contribution('2026-11-03 05:00')));
        self::assertEquals($this->score(1, '2026-11-03 10:00'), $board->add($first, $this->contribution('2026-11-03 23:00')));
        self::assertEquals($this->score(2, '2026-11-04 06:00'), $board->add($first, $this->contribution('2026-11-04 06:00')));
        self::assertEquals($this->score(Score::MAX, '2026-11-04 06:00'), $board->add($this->score(Score::MAX, '2026-11-03 10:00'), $this->contribution('2026-11-04 06:00')));
        self::assertEquals(
            $this->score(3, '2026-11-06 06:00'),
            $board->recount([$this->contribution('2026-11-06 06:00'), $this->contribution('2026-11-03 10:00'), $this->contribution('2026-11-03 11:00'), $this->contribution('2026-11-02 23:00'), $this->contribution('2026-11-06 23:00')]),
        );
    }

    public function testAWindowBoard(): void
    {
        $board = new Board('regular', Measure::Days, new Schedule(PeriodKind::Window, new DateTimeZone('America/Toronto')), ['lesson.done'], window: 7);

        self::assertSame('2026-11-01 00:00 -04:00', $board->windowStart(new DateTimeImmutable('2026-11-07 12:00 UTC'))?->format('Y-m-d H:i P'));
        self::assertSame('2026-11-07 00:00 -05:00', new Board('today', Measure::Count, new Schedule(PeriodKind::Window, new DateTimeZone('America/Toronto')), ['lesson.done'], window: 1)->windowStart(new DateTimeImmutable('2026-11-07 12:00 UTC'))?->format('Y-m-d H:i P'));
        self::assertNull(new Board('month', Measure::Count, new Schedule(PeriodKind::Month), ['lesson.done'])->windowStart(new DateTimeImmutable('2026-11-07 12:00 UTC')));
        self::assertEquals(Period::window(), $board->schedule->at(new DateTimeImmutable('2026-11-07 12:00 UTC')));
        self::assertEquals(Period::window(), $board->schedule->named('window'));
        self::assertNull($board->schedule->named('ever'));
        self::assertNull($board->schedule->previous(new DateTimeImmutable('2026-11-07 12:00 UTC')));
    }

    public function testAWindowCountsOneToAYearOfDays(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('A board ranked over a sliding window counts 1 to 366 days, and only such a board counts any; the board "a" counts 367.');

        new Board('a', Measure::Count, new Schedule(PeriodKind::Window), ['lesson.done'], window: 367);
    }

    public function testAWindowNeedsItsDays(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('the board "a" counts 0.');

        new Board('a', Measure::Count, new Schedule(PeriodKind::Window), ['lesson.done']);
    }

    public function testOnlyAWindowCountsDays(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('the board "a" counts 7.');

        new Board('a', Measure::Count, new Schedule(PeriodKind::Month), ['lesson.done'], window: 7);
    }

    public function testNegativeDaysAreRefused(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('the board "a" counts -1.');

        new Board('a', Measure::Count, new Schedule(PeriodKind::Window), ['lesson.done'], window: -1);
    }

    public function testTheWindowSlides(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-07 12:00 UTC');
        $kit->record('k0', 'member:1', 'lesson.done', '2026-10-25 10:00');
        $kit->record('k1', 'member:1', 'lesson.done', '2026-11-01 10:00');
        $kit->record('k2', 'member:1', 'lesson.done', '2026-11-02 10:00');
        $kit->record('k3', 'member:1', 'lesson.done', '2026-11-02 11:00');
        $kit->record('k4', 'member:1', 'lesson.done', '2026-11-07 10:00');
        $kit->record('k5', 'member:2', 'lesson.done', '2026-11-01 10:00');

        $member = ResourceReference::fromString('member:1');

        self::assertSame(3, $kit->scores->score('regular', 'window', $member)?->value);
        self::assertSame(4, $kit->scores->score('lessons', 'window', $member)?->value);
        self::assertSame(0, $kit->projection->slide(new DateTimeImmutable('2026-11-07 23:00 UTC')));

        self::assertSame(4, $kit->projection->slide(new DateTimeImmutable('2026-11-08 12:00 UTC')));
        self::assertSame(2, $kit->scores->score('regular', 'window', $member)->value);
        self::assertSame(3, $kit->scores->score('lessons', 'window', $member)->value);
        self::assertNull($kit->scores->score('regular', 'window', ResourceReference::fromString('member:2')));
        self::assertSame(1, $kit->scores->score('month', '2026-11', ResourceReference::fromString('member:2'))?->value);
    }

    public function testAnEarlierDayRecordedLaterIsCountedAgain(): void
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'lesson.done', '2026-11-05 15:00');
        $kit->record('k2', 'member:1', 'lesson.done', '2026-11-03 15:00');
        $kit->record('k3', 'member:1', 'lesson.done', '2026-11-03 16:00');

        self::assertEquals(new Score('regular', 'window', ResourceReference::fromString('member:1'), 2, new DateTimeImmutable('2026-11-05 15:00 UTC')), $kit->scores->score('regular', 'window', ResourceReference::fromString('member:1')));
    }

    public function testWithoutAClockTheProjectionKeepsOldGestures(): void
    {
        $boards = Boards::fromArray(['lessons' => ['measure' => 'count', 'verbs' => ['lesson.done'], 'period' => 'window', 'window' => 1]]);
        $scores = new InMemoryScoreStore();
        $kit = new Kit($boards);
        $projection = new LeaderboardProjection($boards, $scores);
        $kit->record('k1', 'member:1', 'lesson.done', '2026-10-01 10:00');

        foreach ($kit->log->read(0, 10, Filter::all()) as $entry) {
            $projection->apply($entry);
        }

        self::assertSame(1, $scores->score('lessons', 'window', ResourceReference::fromString('member:1'))?->value);
        self::assertNull($kit->scores->score('lessons', 'window', ResourceReference::fromString('member:1')));
    }

    public function testATitleIsHeldFromItsThreshold(): void
    {
        $kit = $this->kit();
        $member = ResourceReference::fromString('member:1');

        self::assertSame([], $kit->leaderboards->titles($member));

        foreach (['2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06'] as $index => $day) {
            $kit->record('k'.$index, 'member:1', 'lesson.done', $day.' 15:00');
        }

        self::assertSame(['busy'], $kit->leaderboards->titles($member));

        $kit->record('k9', 'member:1', 'lesson.done', '2026-11-07 10:00');

        self::assertSame(['busy', 'regular'], $kit->leaderboards->titles($member));
        self::assertSame([], $kit->leaderboards->titles(ResourceReference::fromString('member:2')));
        self::assertTrue(new Title('regular', 5)->isHeldWith($this->score(5, '2026-11-03 10:00')));
        self::assertFalse(new Title('regular', 5)->isHeldWith($this->score(4, '2026-11-03 10:00')));
    }

    public function testALocalLegendLeadsTheRankingOfAnObject(): void
    {
        $kit = new Kit(Boards::fromArray([
            'legend' => ['measure' => 'count', 'verbs' => ['game.played'], 'period' => 'window', 'window' => 90, 'per_object' => true, 'title' => ['name' => 'local-legend', 'threshold' => 2, 'leader' => true]],
            'plain' => ['measure' => 'count', 'verbs' => ['game.played'], 'per_object' => true],
        ]));
        $catan = ResourceReference::fromString('game:catan');
        $kit->recordOn('k1', 'member:1', 'game.played', '2026-11-01 10:00', 'game:catan');
        $kit->recordOn('k2', 'member:1', 'game.played', '2026-11-02 10:00', 'game:catan');
        $kit->recordOn('k3', 'member:2', 'game.played', '2026-11-03 10:00', 'game:catan');
        $kit->recordOn('k4', 'member:2', 'game.played', '2026-11-04 10:00', 'game:catan');
        $kit->recordOn('k5', 'member:3', 'game.played', '2026-11-04 10:00', 'game:azul');

        self::assertTrue($kit->leaderboards->holds('legend', ResourceReference::fromString('member:1'), $catan));
        self::assertFalse($kit->leaderboards->holds('legend', ResourceReference::fromString('member:2'), $catan));
        self::assertFalse($kit->leaderboards->holds('legend', ResourceReference::fromString('member:3'), ResourceReference::fromString('game:azul')));
        self::assertFalse($kit->leaderboards->holds('legend', ResourceReference::fromString('member:9'), $catan));
        self::assertFalse($kit->leaderboards->holds('plain', ResourceReference::fromString('member:1'), $catan));
        self::assertSame([], $kit->leaderboards->titles(ResourceReference::fromString('member:1')));

        $kit->recordOn('k6', 'member:2', 'game.played', '2026-11-05 10:00', 'game:catan');

        self::assertFalse($kit->leaderboards->holds('legend', ResourceReference::fromString('member:1'), $catan));
        self::assertTrue($kit->leaderboards->holds('legend', ResourceReference::fromString('member:2'), $catan));
        self::assertFalse(new Title('t', 1)->leader);
    }

    public function testNoTitleBetweenTwoSeasons(): void
    {
        self::assertFalse($this->kit()->leaderboards->holds('between', ResourceReference::fromString('member:1')));
    }

    public function testATitleHasAThreshold(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('A title is held from a score of 1 to 1000000000000000; 0 is not a threshold.');

        new Title('regular', 0);
    }

    public function testATitleHasAThresholdThatIsAScore(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('1000000000000001 is not a threshold.');

        new Title('regular', Score::MAX + 1);
    }

    public function testATitleHasAName(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('"Regular" is not a title name');

        new Title('Regular', 1);
    }

    private function kit(): Kit
    {
        return new Kit(Boards::fromArray([
            'regular' => ['measure' => 'days', 'verbs' => ['lesson.done'], 'period' => 'window', 'window' => 7, 'title' => ['name' => 'regular', 'threshold' => 5]],
            'lessons' => ['measure' => 'count', 'verbs' => ['lesson.done'], 'period' => 'window', 'window' => 7, 'title' => ['name' => 'busy', 'threshold' => 4]],
            'month' => ['measure' => 'count', 'verbs' => ['lesson.done'], 'period' => 'month', 'title' => ['name' => 'busy', 'threshold' => 4]],
            'between' => ['measure' => 'count', 'verbs' => ['lesson.done'], 'period' => 'season', 'seasons' => [['code' => 's', 'start' => '2026-01-01', 'end' => '2026-02-01']], 'title' => ['name' => 'never', 'threshold' => 1]],
        ], 'America/Toronto'));
    }

    private function contribution(string $at): Contribution
    {
        return new Contribution('regular', '2026-11', ResourceReference::fromString('member:1'), 'k'.$at, 1, new DateTimeImmutable($at.' UTC'));
    }

    private function score(int $value, string $at): Score
    {
        return new Score('regular', '2026-11', ResourceReference::fromString('member:1'), $value, new DateTimeImmutable($at.' UTC'));
    }
}
