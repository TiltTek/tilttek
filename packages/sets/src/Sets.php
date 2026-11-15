<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Tilttek\Sets\Contract\CompletionStore;
use Tilttek\Sets\Contract\DefinitionStore;
use Tilttek\Sets\Contract\PendingDeliveries;
use Tilttek\Sets\Event\DeliveryKeepsFailing;
use Tilttek\Sets\Event\SetCompleted;
use Tilttek\Sets\Event\TierReached;
use Tilttek\Sets\Exception\DefinitionConflict;
use Tilttek\Sets\Exception\DeliveryFailed;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Exception\UnknownSet;
use Tilttek\Sets\Exception\UnsupportedStore;

/**
 * The sets of an application: their versions defined, a holder's progress measured against the latest version, and
 * the completions of the set and of its tiers recorded once, each told by an event that carries the rewards declared,
 * until its handlers took it without an exception. It grants nothing: the application hears the events and grants the
 * rewards through the packages it has, in idempotent handlers.
 *
 * The service keeps no state between two calls.
 */
final readonly class Sets
{
    /**
     * The most holders one call to `deliverPending()` tells.
     */
    public const int PENDING_LIMIT = 10_000;

    private ClockInterface $clock;

    private DeliveryBackoff $backoff;

    /**
     * @param ?DeliveryBackoff $backoff how long `deliverPending()` waits before telling again a holder whose delivery
     *                                  failed; the defaults of {@see DeliveryBackoff} when null
     */
    public function __construct(
        private DefinitionStore $definitions,
        private CompletionStore $completions,
        ?ClockInterface $clock = null,
        private ?EventDispatcherInterface $events = null,
        ?DeliveryBackoff $backoff = null,
    ) {
        $this->clock = $clock ?? new UtcClock();
        $this->backoff = $backoff ?? new DeliveryBackoff();
    }

    /**
     * Defines a version of a set; returns false when that version was defined before with the same content.
     *
     * @throws DefinitionConflict when that version was defined with other content, or a later version exists
     */
    public function define(SetDefinition $definition): bool
    {
        $latest = $this->definitions->latest($definition->code);

        if ($latest instanceof SetDefinition && $latest->version > $definition->version) {
            $existing = $this->definitions->version($definition->code, $definition->version);

            if ($existing instanceof SetDefinition && $existing->equals($definition)) {
                return false;
            }

            throw DefinitionConflict::older($definition->code, $definition->version, $latest->version);
        }

        if ($this->definitions->add($definition)) {
            return true;
        }

        $existing = $this->definitions->version($definition->code, $definition->version);

        if ($existing instanceof SetDefinition && $existing->equals($definition)) {
            return false;
        }

        throw DefinitionConflict::rewritten($definition->code, $definition->version);
    }

    /**
     * The latest version of a set, or the version named.
     *
     * @throws UnknownSet when the set, or that version of it, was never defined
     */
    public function definition(string $code, ?int $version = null): SetDefinition
    {
        $definition = null === $version ? $this->definitions->latest($code) : $this->definitions->version($code, $version);

        return $definition ?? throw UnknownSet::named($code, $version);
    }

    /**
     * Where a holder stands in a set, recording nothing.
     *
     * @throws UnknownSet when the set was never defined
     * @throws InvalidSet when the holder is referenced by an e-mail address
     */
    public function progress(string $set, ResourceReference $holder, Holdings $holdings): Progress
    {
        $definition = $this->definition($set);

        return new Progress($definition->evaluate($holdings), $this->completions->of(Identifiers::holder($holder), $set));
    }

    /**
     * Where a holder stands in a set, with the completions reached for the first time recorded, then every completion
     * of the holder in the set not yet delivered told: the set's own first, then its tiers in their order.
     *
     * A completion is marked delivered once its event was dispatched without an exception. When a handler throws, the
     * completion stays undelivered, the next ones are told all the same, and the first exception is thrown again at the
     * end; a later call to `record()` or `deliver()` tells it again. A completion may thus be told more than once — two
     * processes delivering it at the same moment, a handler that failed after granting —: the handlers are idempotent.
     *
     * @throws UnknownSet when the set was never defined
     * @throws InvalidSet when the holder is referenced by an e-mail address
     */
    public function record(string $set, ResourceReference $holder, Holdings $holdings): Progress
    {
        $holder = Identifiers::holder($holder);
        $definition = $this->definition($set);
        $evaluation = $definition->evaluate($holdings);
        $now = $this->clock->now();
        $recorded = [];
        $reached = $evaluation->isComplete() ? [null] : [];

        foreach ($evaluation->tiersReached as $tier) {
            $reached[] = $tier->code;
        }

        foreach ($reached as $tier) {
            $completion = new Completion($set, $holder, $tier, $definition->version, $now);

            if ($this->completions->add($completion)) {
                $recorded[] = $completion;
            }
        }

        $this->tell(array_filter($this->completions->undelivered($holder), static fn (Completion $completion): bool => $completion->set === $set));

        return new Progress($evaluation, $this->completions->of($holder, $set), $recorded);
    }

    /**
     * Tells every completion of a holder not yet delivered, in every set, as `record()` does; returns how many were
     * delivered. An application calls it to resume after a handler failed, without waiting for the holdings to change.
     *
     * @throws InvalidSet when the holder is referenced by an e-mail address
     */
    public function deliver(ResourceReference $holder): int
    {
        return $this->tell($this->completions->undelivered(Identifiers::holder($holder)));
    }

    /**
     * Tells the undelivered completions of the holders that have some, at most `$limit` holders, the one waiting the
     * longest first, as `deliver()` does for each; returns how many completions were delivered. A command or a
     * scheduled task calls it to resume after a handler failed, without knowing which holders are waiting.
     *
     * A holder whose handler throws does not hold the others back: they are told all the same, and the run ends by a
     * `DeliveryFailed` that carries the counts and, as its previous exception, the first exception thrown. The holder
     * is postponed by the `DeliveryBackoff`: the next runs pass it by until its delay is over, a delay that doubles at
     * each failure in a row up to the longest; from its number of failures to report, each failure is also told by a
     * `DeliveryKeepsFailing` event. A delivery that succeeds forgets the holder's failures.
     *
     * @throws InvalidSet       when the limit is below 1 or above {@see self::PENDING_LIMIT}
     * @throws UnsupportedStore when the completion store does not implement {@see PendingDeliveries}
     * @throws DeliveryFailed   when a handler threw for one holder or more
     */
    public function deliverPending(int $limit): int
    {
        if ($limit < 1 || $limit > self::PENDING_LIMIT) {
            throw InvalidSet::because(\sprintf('A pending delivery tells 1 to %d holders; %d is not.', self::PENDING_LIMIT, $limit));
        }

        if (!$this->completions instanceof PendingDeliveries) {
            throw UnsupportedStore::withoutPendingDeliveries($this->completions::class);
        }

        $now = $this->clock->now();
        $holders = \array_slice($this->completions->holdersWithUndelivered($limit, $now), 0, $limit);
        $first = null;
        $delivered = 0;
        $failed = 0;

        foreach ($holders as $holder) {
            [$told, $failure] = $this->dispatch($this->completions->undelivered($holder));
            $delivered += $told;

            if (!$failure instanceof Throwable) {
                $this->completions->forgetFailures($holder);

                continue;
            }

            $first ??= $failure;
            ++$failed;
            $this->postpone($this->completions, $holder, $failure, $now);
        }

        if ($first instanceof Throwable) {
            throw new DeliveryFailed($delivered, $failed, \count($holders), $first);
        }

        return $delivered;
    }

    /**
     * Removes every completion of a holder, for the erasure of a member; returns how many.
     */
    public function eraseHolder(ResourceReference $holder): int
    {
        return $this->completions->eraseHolder($holder);
    }

    /**
     * Postpones a holder whose delivery failed and reports the failure when it is one too many. A listener of the report
     * that throws does not stop the run, which already ends by a `DeliveryFailed`.
     */
    private function postpone(PendingDeliveries $store, ResourceReference $holder, Throwable $failure, DateTimeImmutable $now): void
    {
        $failures = $store->failedDeliveries($holder) + 1;
        $nextAttemptAt = $this->backoff->nextAttempt($failures, $now);
        $store->postpone($holder, $failures, $nextAttemptAt);

        if (!$this->backoff->reports($failures)) {
            return;
        }

        try {
            $this->events?->dispatch(new DeliveryKeepsFailing($holder, $failures, $nextAttemptAt, $failure));
        } catch (Throwable) {
            // The failure of the delivery itself is thrown at the end of the run.
        }
    }

    /**
     * Dispatches the event of each completion and marks it delivered; a completion whose handler throws stays
     * undelivered, and the first exception is thrown again once the others are told.
     *
     * @param array<Completion> $completions
     *
     * @return int how many were delivered
     */
    private function tell(array $completions): int
    {
        [$delivered, $failure] = $this->dispatch($completions);

        if ($failure instanceof Throwable) {
            throw $failure;
        }

        return $delivered;
    }

    /**
     * Dispatches the event of each completion and marks it delivered; a completion whose handler throws stays
     * undelivered, and the next ones are told all the same.
     *
     * @param array<Completion> $completions
     *
     * @return array{int, ?Throwable} how many were delivered, and the first exception a handler threw
     */
    private function dispatch(array $completions): array
    {
        $failure = null;
        $delivered = 0;

        foreach ($completions as $completion) {
            try {
                $this->events?->dispatch($this->event($completion));
            } catch (Throwable $thrown) {
                $failure ??= $thrown;

                continue;
            }

            $this->completions->markDelivered($completion);
            ++$delivered;
        }

        return [$delivered, $failure];
    }

    /**
     * The event of a completion, with the rewards of the version of the set it was earned under.
     */
    private function event(Completion $completion): SetCompleted|TierReached
    {
        $definition = $this->definition($completion->set, $completion->version);

        return null === $completion->tier
            ? new SetCompleted($completion, $definition->rewards)
            : new TierReached($completion, $definition->tier($completion->tier)->rewards ?? []);
    }
}
