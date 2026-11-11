<?php

declare(strict_types=1);

namespace Tilttek\Activity;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Activity\Exception\IdempotencyConflict;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Exception\UnknownVerb;
use Tilttek\Activity\Log\ActivityLog;

/**
 * The entry of the members' activity, in one object: an application records each gesture once, cancels it when it is
 * undone, and the packages that reward gestures read what it recorded.
 *
 * ```php
 * $journal->record(Activity::of('game:812:player:44', 'member:44', 'game.played', $playedAt, 'game:812'));
 * $journal->cancel(IdempotencyKey::fromString('game:812:player:44'));
 * $journal->totals(Filter::all()->member($member)->verbs('game.*'));
 * ```
 *
 * The journal stamps each entry with its clock, checks the verb against the declared verbs, and tells its observers
 * of each new entry. It records a gesture dated within its window: at most `$past` before its clock — thirty days by
 * default, no bound when null — and at most `$ahead` after it — five minutes by default. A moment the client gave,
 * from an action made offline, cannot reach far into the past to collect the caps of days gone by, nor far from now
 * to make the streaks and periods count units by the billion.
 */
final readonly class Journal
{
    /**
     * How long before the clock a gesture may be dated, by default, as an ISO 8601 duration.
     */
    public const string DEFAULT_PAST = 'P30D';

    /**
     * How long after the clock a gesture may be dated, by default, as an ISO 8601 duration.
     */
    public const string DEFAULT_AHEAD = 'PT5M';

    /**
     * @var list<JournalObserver>
     */
    private array $observers;

    /**
     * @param iterable<JournalObserver> $observers
     */
    public function __construct(
        private ActivityLog $log,
        private ClockInterface $clock,
        private Verbs $verbs = new Verbs(),
        iterable $observers = [],
        private ?DateInterval $past = new DateInterval(self::DEFAULT_PAST),
        private DateInterval $ahead = new DateInterval(self::DEFAULT_AHEAD),
    ) {
        $list = [];

        foreach ($observers as $observer) {
            $list[] = $observer;
        }

        $this->observers = $list;
    }

    /**
     * Records the activity once: recorded again under the same key, it returns the entry already there.
     *
     * @throws UnknownVerb         when the verbs are declared and this one is not
     * @throws InvalidActivity     when the gesture is dated outside the journal's window
     * @throws IdempotencyConflict when the key is recorded for another gesture
     */
    public function record(Activity $activity): Appended
    {
        $this->verbs->check($activity->verb);
        $now = $this->clock->now();
        $this->checkMoment($activity, $now);

        return $this->told($this->log->append($activity, $now));
    }

    /**
     * Cancels the activity recorded under the key, once; null when none is recorded.
     */
    public function cancel(IdempotencyKey|string $key): ?Appended
    {
        $appended = $this->log->cancel(\is_string($key) ? IdempotencyKey::fromString($key) : $key, $this->clock->now());

        return $appended instanceof Appended ? $this->told($appended) : null;
    }

    public function find(IdempotencyKey|string $key): ?Entry
    {
        return $this->log->find(\is_string($key) ? IdempotencyKey::fromString($key) : $key);
    }

    public function isCancelled(IdempotencyKey|string $key): bool
    {
        return $this->log->isCancelled(\is_string($key) ? IdempotencyKey::fromString($key) : $key);
    }

    /**
     * @return list<Entry>
     */
    public function read(int $after, int $limit, ?Filter $filter = null): array
    {
        return $this->log->read($after, $limit, $filter ?? Filter::all());
    }

    public function totals(Filter $filter): Totals
    {
        return $this->log->totals($filter);
    }

    /**
     * Erases a member's activity: the entries of their gestures, and their reference as the object of others'. What
     * each package derived from it is erased by that package.
     */
    public function erase(ResourceReference $member): Erasure
    {
        return $this->log->eraseMember($member);
    }

    private function checkMoment(Activity $activity, DateTimeImmutable $now): void
    {
        $utc = new DateTimeZone('UTC');
        $earliest = $this->past instanceof DateInterval ? $now->sub($this->past)->setTimezone($utc) : null;
        $latest = $now->add($this->ahead)->setTimezone($utc);
        $at = $activity->occurredAt->getTimestamp();

        if ($at > $latest->getTimestamp() || ($earliest instanceof DateTimeImmutable && $at < $earliest->getTimestamp())) {
            throw InvalidActivity::because(\sprintf('The activity "%s" is dated %s, outside the journal\'s window, from %s to %s.', $activity->key->value, $activity->occurredAt->setTimezone($utc)->format(DateTimeInterface::ATOM), $earliest?->format(DateTimeInterface::ATOM) ?? 'the beginning', $latest->format(DateTimeInterface::ATOM)));
        }
    }

    private function told(Appended $appended): Appended
    {
        if ($appended->new) {
            foreach ($this->observers as $observer) {
                $observer->appended($appended->entry);
            }
        }

        return $appended;
    }
}
