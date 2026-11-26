<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards\Tests\Unit;

use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Entry;
use Tilttek\CheckInRewards\AttendanceCriterion;
use Tilttek\CheckInRewards\Exception\InvalidCriterion;
use Tilttek\CheckInRewards\Tests\Unit\Fixture\Door;
use Tilttek\Quests\QuestReader;
use Tilttek\Quests\Step;

#[CoversClass(AttendanceCriterion::class)]
#[CoversClass(InvalidCriterion::class)]
final class AttendanceCriterionTest extends TestCase
{
    public function testTheCriteriaAreWrittenForBadgesAndQuests(): void
    {
        $event = ResourceReference::of('event', 'con');

        self::assertSame(['count' => ['verbs' => ['attendance.session'], 'context' => ['event' => 'event:con'], 'times' => 5]], AttendanceCriterion::sessions(5, $event)->toBadgeCriterion());
        self::assertSame(['count' => ['verbs' => ['attendance.session'], 'context' => [], 'times' => 10]], AttendanceCriterion::sessions(10)->toBadgeCriterion());
        self::assertSame(['code' => 'every-day', 'verbs' => ['attendance.day'], 'context' => ['event' => 'event:con'], 'count' => 3], AttendanceCriterion::everyDay($event, 3)->toQuestStep('every-day'));
        self::assertSame(['code' => 'explorer', 'verbs' => ['attendance.place'], 'context' => [], 'count' => 4], AttendanceCriterion::distinctPlaces(4)->toQuestStep('explorer'));
        self::assertSame(['code' => 'explorer', 'verbs' => ['attendance.place'], 'context' => ['event' => 'event:con'], 'count' => 4], AttendanceCriterion::distinctPlaces(4, $event)->toQuestStep('explorer'));
        self::assertSame(['count' => ['verbs' => ['attendance.place'], 'context' => ['event' => 'event:con', 'place' => 'table-12'], 'times' => 1]], AttendanceCriterion::place('table-12', $event)->toBadgeCriterion());
        self::assertSame(['count' => ['verbs' => ['attendance.place'], 'context' => ['place' => 'table-12'], 'times' => 1]], AttendanceCriterion::place('table-12')->toBadgeCriterion());
        self::assertSame(['count' => ['verbs' => ['attendance.week'], 'context' => [], 'times' => 8]], AttendanceCriterion::weeks(8)->toBadgeCriterion());
    }

    public function testTheStepsOfAQuestCountTheAttendanceTheRecorderRecorded(): void
    {
        $door = new Door(new DateTimeZone('America/Montreal'), '2027-03-05 15:00:00 UTC');
        $door->scan('44', 'con.panel-1.table-1');
        $door->scan('44', 'con.panel-2.table-2');
        $door->scan('44', 'fest.panel-1.table-1');
        $door->clock->modify('+1 day');
        $door->scan('44', 'con.panel-3.table-1');
        $door->clock->modify('+20 days');
        $door->scan('44', 'shop');

        $event = ResourceReference::of('event', 'con');
        $quest = new QuestReader()->read('attendance', ['steps' => [
            AttendanceCriterion::sessions(3, $event)->toQuestStep('passport'),
            AttendanceCriterion::everyDay($event, 2)->toQuestStep('every-day'),
            AttendanceCriterion::distinctPlaces(2, $event)->toQuestStep('explorer'),
            AttendanceCriterion::place('table-2', $event)->toQuestStep('table-2'),
            AttendanceCriterion::weeks(2)->toQuestStep('weeks'),
            AttendanceCriterion::sessions(5)->toQuestStep('anywhere'),
        ]]);

        $entries = $door->journal->read(0, 100);
        $counts = array_map(
            static fn (Step $step): int => \count(array_filter($entries, static fn (Entry $entry): bool => $step->accepts($entry->activity))),
            $quest->steps,
        );

        self::assertSame([3, 2, 2, 1, 2, 5], $counts);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function outOfBounds(): iterable
    {
        yield 'zero' => [0];
        yield 'past the maximum' => [AttendanceCriterion::MAX + 1];
    }

    #[DataProvider('outOfBounds')]
    public function testACountIsBounded(int $count): void
    {
        $this->expectExceptionObject(new InvalidCriterion(\sprintf('An attendance criterion counts 1 to 1000000000; this one counts %d.', $count)));
        AttendanceCriterion::weeks($count);
    }

    public function testTheBoundsThemselvesAreCounts(): void
    {
        self::assertSame(1, AttendanceCriterion::weeks(1)->count);
        self::assertSame(AttendanceCriterion::MAX, AttendanceCriterion::weeks(AttendanceCriterion::MAX)->count);
    }

    public function testAnEventMustFitInTheContext(): void
    {
        self::assertSame(['event' => 'e:'.str_repeat('x', 98)], AttendanceCriterion::weeks(1)->context + AttendanceCriterion::sessions(1, ResourceReference::of('e', str_repeat('x', 98)))->context);

        $this->expectExceptionObject(new InvalidCriterion('The reference of an event is at most 100 bytes; "e:'.str_repeat('x', 99).'" is 101.'));
        AttendanceCriterion::sessions(1, ResourceReference::of('e', str_repeat('x', 99)));
    }

    public function testAPlaceMustFitInTheContext(): void
    {
        self::assertSame('p'.str_repeat('x', 99), AttendanceCriterion::place('p'.str_repeat('x', 99))->context['place']);

        $this->expectExceptionObject(new InvalidCriterion('A place is 1 to 100 bytes; this one is 101.'));
        AttendanceCriterion::place(str_repeat('x', 101));
    }

    public function testAPlaceIsNotEmpty(): void
    {
        $this->expectExceptionObject(new InvalidCriterion('A place is 1 to 100 bytes; this one is 0.'));
        AttendanceCriterion::place('');
    }
}
