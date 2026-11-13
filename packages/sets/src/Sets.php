<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Sets\Contract\CompletionStore;
use Tilttek\Sets\Contract\DefinitionStore;
use Tilttek\Sets\Event\SetCompleted;
use Tilttek\Sets\Event\TierReached;
use Tilttek\Sets\Exception\DefinitionConflict;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Exception\UnknownSet;

/**
 * The sets of an application: their versions defined, a holder's progress measured against the latest version, and
 * the completions of the set and of its tiers recorded once, each told by an event that carries the rewards declared.
 * It grants nothing: the application hears the events and grants the rewards through the packages it has.
 *
 * The service keeps no state between two calls.
 */
final readonly class Sets
{
    private ClockInterface $clock;

    public function __construct(
        private DefinitionStore $definitions,
        private CompletionStore $completions,
        ?ClockInterface $clock = null,
        private ?EventDispatcherInterface $events = null,
    ) {
        $this->clock = $clock ?? new UtcClock();
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
     * Where a holder stands in a set, with the completions reached for the first time recorded and told: the set's own
     * first, then its tiers in their order.
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
        $events = [];

        if ($evaluation->isComplete()) {
            $completion = new Completion($set, $holder, null, $definition->version, $now);

            if ($this->completions->add($completion)) {
                $recorded[] = $completion;
                $events[] = new SetCompleted($completion, $definition->rewards);
            }
        }

        foreach ($evaluation->tiersReached as $tier) {
            $completion = new Completion($set, $holder, $tier->code, $definition->version, $now);

            if ($this->completions->add($completion)) {
                $recorded[] = $completion;
                $events[] = new TierReached($completion, $tier->rewards);
            }
        }

        foreach ($events as $event) {
            $this->events?->dispatch($event);
        }

        return new Progress($evaluation, $this->completions->of($holder, $set), $recorded);
    }

    /**
     * Removes every completion of a holder, for the erasure of a member; returns how many.
     */
    public function eraseHolder(ResourceReference $holder): int
    {
        return $this->completions->eraseHolder($holder);
    }
}
