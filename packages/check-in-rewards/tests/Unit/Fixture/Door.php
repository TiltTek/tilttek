<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards\Tests\Unit\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Checkin\CheckIn;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\DeviceId;
use Phil\Checkin\Direction;
use Phil\Checkin\InMemoryCheckInLedger;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\MockClock;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\CheckInRewards\AttendanceRecorder;
use Tilttek\CheckInRewards\Contract\PresenceResolver;
use Tilttek\CheckInRewards\Presence;

/**
 * A door of a convention for the tests: a ledger, a journal on a mock clock, and a resolver that knows the credentials
 * `ticket:<member>` of the members it is given and the checkpoints `<event>.<object>.<place>`.
 */
final class Door implements PresenceResolver
{
    public readonly InMemoryCheckInLedger $ledger;

    public readonly Journal $journal;

    public readonly InMemoryActivityLog $log;

    public readonly MockClock $clock;

    public readonly AttendanceRecorder $recorder;

    /**
     * @var array<string, true>
     */
    private array $erased = [];

    private int $sequence = 0;

    public function __construct(
        private readonly DateTimeZone $timezone = new DateTimeZone('UTC'),
        string $now = '2027-03-05 18:00:00 UTC',
    ) {
        $this->ledger = new InMemoryCheckInLedger();
        $this->log = new InMemoryActivityLog();
        $this->clock = new MockClock(new DateTimeImmutable($now));
        $this->journal = new Journal($this->log, $this->clock);
        $this->recorder = new AttendanceRecorder($this->ledger, $this, $this->journal);
    }

    public function erase(string $member): void
    {
        $this->erased[$member] = true;
    }

    /**
     * Scans the member's credential at the checkpoint, records it in the ledger and hands its identifier to the recorder.
     */
    public function scan(string $member, string $checkpoint, ?string $scannedAt = null, ?string $scan = null, ?string $recordedAt = null): CheckIn
    {
        $now = $this->clock->now();
        $checkIn = CheckIn::restore(
            ScanId::of($scan ?? 'scan-'.(++$this->sequence)),
            'ticket:'.$member,
            CheckpointId::of($checkpoint),
            Direction::In,
            ScanOutcome::Admitted,
            DeviceId::of('desk-1'),
            null === $scannedAt ? $now : new DateTimeImmutable($scannedAt),
            null === $recordedAt ? $now : new DateTimeImmutable($recordedAt),
            $this->ledger->attendance('ticket:'.$member)->version + 1,
        );
        $this->ledger->append($checkIn);
        $this->recorder->record($checkIn->id);

        return $checkIn;
    }

    #[Override]
    public function resolve(CheckIn $checkIn): ?Presence
    {
        $member = substr($checkIn->credentialId, \strlen('ticket:'));

        if (!str_starts_with($checkIn->credentialId, 'ticket:') || isset($this->erased[$member])) {
            return null;
        }

        $parts = explode('.', $checkIn->checkpoint->value);

        return new Presence(
            ResourceReference::of('member', $member),
            ResourceReference::of('session', $parts[1] ?? $parts[0]),
            'shop' === $parts[0] ? null : ResourceReference::of('event', $parts[0]),
            $parts[2] ?? null,
            $parts[3] ?? null,
            $this->timezone,
        );
    }
}
