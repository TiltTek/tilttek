<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\CheckInRewards\AttendanceReader;
use Tilttek\CheckInRewards\AttendanceSummary;
use Tilttek\CheckInRewards\Exception\AccessDenied;
use Tilttek\CheckInRewards\Tests\Unit\Fixture\Door;

#[CoversClass(AttendanceReader::class)]
#[CoversClass(AttendanceSummary::class)]
#[CoversClass(AccessDenied::class)]
final class AttendanceReaderTest extends TestCase
{
    public function testAMemberReadsTheirAttendanceOfAnEventAndOfAll(): void
    {
        $door = new Door(now: '2027-03-05 10:00:00 UTC');
        $door->scan('44', 'con.panel-1.table-1');
        $door->scan('44', 'con.panel-2.table-2');
        $door->scan('44', 'con.panel-2.table-2');
        $door->clock->modify('+1 day');
        $door->scan('44', 'con.panel-3.table-1');
        $door->scan('45', 'con.panel-4.table-4');
        $door->clock->modify('+14 days');
        $door->scan('44', 'fest.stage.stand-9');

        $member = ResourceReference::of('member', '44');
        $reader = new AttendanceReader($door->journal);

        // The weeks are the member's, whatever the event: a week spans events.
        $con = $reader->summary($member, $member, ResourceReference::of('event', 'con'));
        self::assertSame([3, 2, 2, 2], [$con->sessions, $con->days, $con->places, $con->weeks]);

        $all = $reader->summary($member, $member);
        self::assertSame([4, 3, 3, 2], [$all->sessions, $all->days, $all->places, $all->weeks]);
    }

    public function testAMissedWeekTakesNothingAway(): void
    {
        $door = new Door(now: '2027-03-01 10:00:00 UTC');
        $member = ResourceReference::of('member', '44');
        $reader = new AttendanceReader($door->journal);

        // Weeks 9, 10, 12 and 16 of 2027: two gaps, and the series goes on where it was.
        foreach ([7, 14, 28, 0] as $gap) {
            $door->scan('44', 'shop');
            $door->clock->modify(\sprintf('+%d days', $gap));
        }

        self::assertSame(4, $reader->summary($member, $member)->weeks);
    }

    public function testErasingTheMemberInTheJournalErasesTheirAttendance(): void
    {
        $door = new Door();
        $door->scan('44', 'con.panel-1.table-1');

        $member = ResourceReference::of('member', '44');
        $door->journal->erase($member);

        $summary = new AttendanceReader($door->journal)->summary($member, $member);
        self::assertSame([0, 0, 0, 0], [$summary->sessions, $summary->days, $summary->places, $summary->weeks]);
    }

    public function testNoOneReadsTheAttendanceOfSomeoneElse(): void
    {
        $door = new Door();
        $door->scan('44', 'con.panel-1.table-1');

        $this->expectExceptionObject(new AccessDenied('"member:45" cannot read the attendance of "member:44": a member\'s attendance is theirs alone.'));
        new AttendanceReader($door->journal)->summary(ResourceReference::of('member', '45'), ResourceReference::of('member', '44'));
    }

    public function testAnEmptySummaryIsZero(): void
    {
        $summary = new AttendanceSummary();

        self::assertSame([0, 0, 0, 0], [$summary->sessions, $summary->days, $summary->places, $summary->weeks]);
    }
}
