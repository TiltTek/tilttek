<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards;

use DateTimeImmutable;
use Phil\Checkin\CheckIn;
use Phil\Checkin\Contract\CheckInLedger;
use Phil\Checkin\ScanId;
use Phil\Checkin\ScanOutcome;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Context;
use Tilttek\Activity\Exception\IdempotencyConflict;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Exception\UnknownVerb;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Verb;
use Tilttek\CheckInRewards\Contract\PresenceResolver;

/**
 * Turns each admission at the door into attendance in the journal of `tilttek/activity`. It is the library's only way
 * in, and it takes nothing but a scan's identifier: it reads the check-in as the ledger of `phil/checkin` keeps it, so
 * that a presence is never one a phone declares. The application calls it from its listener of the check-in event,
 * or from the handler of a message that carries the scan's identifier.
 *
 * It records an admission — outcome admitted, no conflict between the device and the server — once under the scan's
 * identifier, after asking the application what it means: a scan handed again, a listener run twice, adds nothing.
 * With it, it records the first admission of the member to the session, on the day, at the place and in the week, each
 * once, under a key drawn from what makes it unique.
 *
 * A check-out, a refusal, a conflict, a scan the ledger does not hold and an admission the application resolves to no
 * one are left out. It keeps nothing between two calls.
 */
final readonly class AttendanceRecorder
{
    public function __construct(
        private CheckInLedger $ledger,
        private PresenceResolver $resolver,
        private Journal $journal,
    ) {
    }

    /**
     * Records the scan's admission, once; whether the scan is an admission that rewards someone.
     *
     * @throws UnknownVerb         when the journal declares its verbs without `attendance.*`
     * @throws InvalidActivity     when the scan is dated outside the journal's window
     * @throws IdempotencyConflict when the scan is resolved to another presence than the first time
     */
    public function record(ScanId $scan): bool
    {
        $checkIn = $this->ledger->find($scan);

        // A check-in admitted is an entry: the ledger refuses an admission out.
        if (!$checkIn instanceof CheckIn || ScanOutcome::Admitted !== $checkIn->outcome || $checkIn->conflict instanceof ScanOutcome) {
            return false;
        }

        $presence = $this->resolver->resolve($checkIn);

        if (!$presence instanceof Presence) {
            return false;
        }

        $this->attend($checkIn, $presence);

        return true;
    }

    private function attend(CheckIn $checkIn, Presence $presence): void
    {
        // A device whose clock runs ahead cannot date a scan after the server received it.
        $at = min($checkIn->scannedAt, $checkIn->recordedAt);
        $local = $at->setTimezone($presence->timezone);
        $day = $local->format('Y-m-d');
        $event = $presence->event?->toString();
        $member = $presence->member;

        $this->journal->record(new Activity(
            IdempotencyKey::fromString('check-in:'.$checkIn->id->value),
            $member,
            Verb::fromString(AttendanceVerbs::RECORDED),
            $at,
            $presence->object,
            context: $this->context(['event' => $event, 'place' => $presence->place, 'track' => $presence->track, 'day' => $day]),
        ));

        $this->first(AttendanceVerbs::SESSION, [$event, $presence->object->toString()], $presence, $at, ['event' => $event]);
        $this->first(AttendanceVerbs::DAY, [$event, $day], $presence, $at, ['event' => $event, 'day' => $day]);

        if (null !== $presence->place) {
            $this->first(AttendanceVerbs::PLACE, [$event, $presence->place], $presence, $at, ['event' => $event, 'place' => $presence->place]);
        }

        // A week spans events: its first admission is the member's, whatever event it belongs to.
        $week = $local->format('o-\\WW');
        $this->first(AttendanceVerbs::WEEK, [$week], $presence, $at, ['week' => $week], false);
    }

    /**
     * Records the first admission of the member to what the parts name, its object the event when it is one of an
     * event.
     *
     * @param list<?string>          $parts
     * @param array<string, ?string> $context
     */
    private function first(string $verb, array $parts, Presence $presence, DateTimeImmutable $at, array $context, bool $ofEvent = true): void
    {
        $this->journal->record(new Activity(
            $this->key($verb, $presence->member, $parts),
            $presence->member,
            Verb::fromString($verb),
            $at,
            $ofEvent ? $presence->event : null,
            context: $this->context($context),
        ));
    }

    /**
     * The key of a first time: the verb, then a digest of the member and the parts, each prefixed by its length so
     * that no two lists of parts write the same text.
     *
     * @param list<?string> $parts
     */
    private function key(string $verb, ResourceReference $member, array $parts): IdempotencyKey
    {
        $text = '';

        foreach ([$member->toString(), ...$parts] as $part) {
            $text .= \strlen($part ?? '').':'.$part.';';
        }

        return IdempotencyKey::fromString($verb.':'.hash('sha256', $text));
    }

    /**
     * @param array<string, ?string> $values
     */
    private function context(array $values): Context
    {
        return new Context(array_filter($values, static fn (?string $value): bool => null !== $value));
    }
}
