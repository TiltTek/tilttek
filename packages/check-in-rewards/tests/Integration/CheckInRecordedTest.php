<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards\Tests\Integration;

use DateTimeImmutable;
use Override;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\Offline\OfflineScan;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use Phil\CheckinBundle\Application\Event\CheckInRecorded;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\CheckInRewards\AttendanceReader;
use Tilttek\CheckInRewards\AttendanceRecorder;
use Tilttek\CheckInRewards\Contract\PresenceResolver;
use Tilttek\CheckInRewards\Presence;

/**
 * The recorder called from a listener of `CheckInRecorded` registered on an event dispatcher, as an application
 * registers it, and the events of the bundle built by the bundle's own named constructors.
 */
#[CoversClass(AttendanceRecorder::class)]
final class CheckInRecordedTest extends TestCase
{
    public function testTheEventsOfTheBundleBecomeAttendance(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2027-03-05 18:00:00 UTC'));
        $ledger = new InMemoryCheckInLedger();
        $journal = new Journal(new InMemoryActivityLog(), $clock);
        $resolver = new class implements PresenceResolver {
            #[Override]
            public function resolve(CheckIn $checkIn): Presence
            {
                return new Presence(ResourceReference::fromString('member:'.$checkIn->credentialId), ResourceReference::of('session', $checkIn->checkpoint->value), ResourceReference::of('event', 'con'), $checkIn->checkpoint->value);
            }
        };
        $dispatcher = new EventDispatcher();
        $recorder = new AttendanceRecorder($ledger, $resolver, $journal);
        $dispatcher->addListener(CheckInRecorded::class, static function (CheckInRecorded $event) use ($recorder): void {
            $recorder->record($event->id);
        });

        $online = CheckIn::restore(ScanId::of('a1'), '44', CheckpointId::of('panel-1'), Direction::In, ScanOutcome::Admitted, DeviceId::of('desk-1'), $clock->now(), $clock->now(), 1);
        $ledger->append($online);
        $dispatcher->dispatch(CheckInRecorded::online($online));

        $scan = OfflineScan::of(ScanId::of('b2'), '44', Direction::In, ScanOutcome::Admitted, $clock->now()->modify('-1 day'));
        $ledger->append(CheckIn::restore($scan->id, '44', CheckpointId::of('panel-2'), Direction::In, ScanOutcome::Admitted, DeviceId::of('desk-2'), $scan->scannedAt, $clock->now(), 2, true));
        $dispatcher->dispatch(CheckInRecorded::synchronised($scan, CheckpointId::of('panel-2'), DeviceId::of('desk-2'), false));
        $dispatcher->dispatch(CheckInRecorded::synchronised($scan, CheckpointId::of('panel-2'), DeviceId::of('desk-2'), false));

        $member = ResourceReference::of('member', '44');
        $summary = new AttendanceReader($journal)->summary($member, $member, ResourceReference::of('event', 'con'));
        self::assertSame([2, 2, 2, 1], [$summary->sessions, $summary->days, $summary->places, $summary->weeks]);
    }
}
