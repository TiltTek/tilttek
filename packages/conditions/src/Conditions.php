<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Tilttek\Conditions\Contract\ConditionObserver;
use Tilttek\Conditions\Contract\ConditionStore;
use Tilttek\Conditions\Effect\AppliedModifier;
use Tilttek\Conditions\Effect\EffectCalculator;
use Tilttek\Conditions\Effect\StatValue;
use Tilttek\Conditions\Event\ConditionApplied;
use Tilttek\Conditions\Event\ConditionChanged;
use Tilttek\Conditions\Event\ConditionEnded;
use Tilttek\Conditions\Event\ConditionEvent;
use Tilttek\Conditions\Exception\ConditionConflict;
use Tilttek\Conditions\Exception\ConditionNotFound;
use Tilttek\Conditions\Exception\ConditionRefused;
use Tilttek\Conditions\Exception\InvalidCondition;
use Tilttek\Conditions\Internal\Identifier;
use Tilttek\Conditions\Internal\Millis;
use Tilttek\Conditions\View\ConditionState;
use Tilttek\Conditions\View\ConditionsView;

/**
 * The service of the conditions: it applies a condition of the catalogue to a character by its stacking rule, removes
 * it or changes its value, counts the turns, rounds and game time the table tells it, ends what runs out in real time,
 * and says what the conditions do to the statistics. It reads from the store, changes, keeps, then tells the observer
 * what happened, each fact once. It keeps no state between calls: everything is in the store, and the time comes from
 * the PSR-20 clock.
 *
 * The service trusts its caller to say who may apply or remove a condition.
 */
final readonly class Conditions
{
    /** The most characters a turn, a round or a time advance counts for. */
    public const int MAX_PARTICIPANTS = 200;

    public function __construct(
        private ConditionStore $store,
        private ConditionCatalogue $catalogue,
        private ClockInterface $clock,
        private ?ConditionObserver $observer = null,
        private EffectCalculator $calculator = new EffectCalculator(),
    ) {
    }

    /**
     * The character's conditions as kept, none at version 0 when none were ever kept.
     *
     * @throws InvalidCondition when the identifier is not one
     */
    public function conditions(string $character): CharacterConditions
    {
        return $this->store->conditions(Identifier::check($character, 'a character')) ?? new CharacterConditions($character);
    }

    /**
     * Applies a condition to a character by its stacking rule: a new instance, one in place changed, or nothing when
     * the rule keeps what is in place. The conditions already past their end in real time end first.
     *
     * @param ?int      $value    1 to the condition's highest value, given when and only when it has a value
     * @param ?Duration $duration how long it lasts; the condition's own duration, or until removed, when null
     * @param ?string   $source   the character or the object that caused it, required for a duration counted on its turns
     *
     * @throws InvalidCondition  when an identifier is not one, the value is missing, given or out of its bounds, or the
     *                           duration counts the turns of a source not given
     * @throws ConditionNotFound when the catalogue has no such condition
     * @throws ConditionRefused  when the character has the most conditions, or the most instances of this one
     * @throws ConditionConflict when the conditions changed since they were read
     */
    public function apply(string $character, string $condition, ?int $value = null, ?Duration $duration = null, ?string $source = null): CharacterConditions
    {
        $before = $this->conditions($character);
        $definition = $this->catalogue->get(Identifier::check($condition, 'a condition'));
        $duration = $this->checked($definition, $value, $duration, $source);
        $now = $this->clock->now();
        $events = [];
        $active = $this->pruned($before, Millis::of($now), $events);
        $expiresAt = DurationKind::RealTime === $duration->kind ? Millis::at(Millis::of($now) + $duration->amount) : null;
        $existing = array_values(array_filter($active, static fn (ActiveCondition $one): bool => $one->condition === $condition));
        $first = $existing[0] ?? null;

        if ($first instanceof ActiveCondition && \in_array($definition->stacking, [Stacking::Highest, Stacking::Sum, Stacking::Ignore], true)) {
            $current = $first->value ?? 0;
            $sum = (int) $first->value + (int) $value;
            $changed = match ($definition->stacking) {
                Stacking::Highest => ($value ?? 0) >= $current
                    ? new ActiveCondition($first->id, $condition, $value, $source, $duration, $now, $expiresAt)
                    : null,
                Stacking::Sum => new ActiveCondition($first->id, $condition, min((int) $definition->maxValue, $sum), $source, $duration, $first->appliedAt, $expiresAt),
                default => null,
            };
            if ($changed instanceof ActiveCondition) {
                $active = array_map(static fn (ActiveCondition $one): ActiveCondition => $one->id === $first->id ? $changed : $one, $active);
                $events[] = new ConditionChanged($before->character, $first->id, $condition);
            }

            return [] === $events ? $before : $this->keep($before->with($active, $before->issued, $before->lastTick), $events);
        }

        if (Stacking::Replace === $definition->stacking) {
            foreach ($existing as $one) {
                $events[] = new ConditionEnded($before->character, $one->id, $condition, EndReason::Replaced);
            }

            $active = array_values(array_filter($active, static fn (ActiveCondition $one): bool => $one->condition !== $condition));
        } elseif (\count($existing) >= $definition->maxInstances) {
            throw ConditionRefused::because(\sprintf('The character %s has the most instances of %s.', $before->character, $condition));
        }

        if (\count($active) >= CharacterConditions::MAX_ACTIVE) {
            throw ConditionRefused::because(\sprintf('The character %s has the most conditions.', $before->character));
        }

        $issued = $before->issued + 1;
        $instance = 'i'.$issued;
        $active[] = new ActiveCondition($instance, $condition, $value, $source, $duration, $now, $expiresAt);
        $events[] = new ConditionApplied($before->character, $instance, $condition);

        return $this->keep($before->with($active, $issued, $before->lastTick), $events);
    }

    /**
     * Removes one application of a condition.
     *
     * @throws InvalidCondition  when an identifier is not one
     * @throws ConditionNotFound when the character has no such instance
     * @throws ConditionConflict when the conditions changed since they were read
     */
    public function remove(string $character, string $instance): CharacterConditions
    {
        $before = $this->conditions($character);
        $target = $before->instance($instance) ?? throw ConditionNotFound::instance($before->character, $instance);
        $active = array_values(array_filter($before->active, static fn (ActiveCondition $one): bool => $one->id !== $target->id));

        return $this->keep($before->with($active, $before->issued, $before->lastTick), [new ConditionEnded($before->character, $target->id, $target->condition, EndReason::Removed)]);
    }

    /**
     * Removes every application of a condition, a condition the catalogue no longer has too; nothing changes when the
     * character has none.
     *
     * @throws InvalidCondition  when an identifier is not one
     * @throws ConditionConflict when the conditions changed since they were read
     */
    public function removeCondition(string $character, string $condition): CharacterConditions
    {
        $before = $this->conditions($character);
        Identifier::check($condition, 'a condition');
        $events = [];
        $active = [];
        foreach ($before->active as $one) {
            if ($one->condition === $condition) {
                $events[] = new ConditionEnded($before->character, $one->id, $condition, EndReason::Removed);
            } else {
                $active[] = $one;
            }
        }

        return [] === $events ? $before : $this->keep($before->with($active, $before->issued, $before->lastTick), $events);
    }

    /**
     * Sets the value of an application: 0 removes it; the same value changes nothing.
     *
     * @throws InvalidCondition  when an identifier is not one, or the value is out of 0 to the condition's highest
     * @throws ConditionNotFound when the character has no such instance
     * @throws ConditionRefused  when the condition has no value, or the catalogue no longer has it
     * @throws ConditionConflict when the conditions changed since they were read
     */
    public function setValue(string $character, string $instance, int $value): CharacterConditions
    {
        $before = $this->conditions($character);
        $target = $before->instance($instance) ?? throw ConditionNotFound::instance($before->character, $instance);
        $max = $this->catalogue->find($target->condition)->maxValue ?? throw ConditionRefused::because(\sprintf('The condition %s has no value.', $target->condition));
        if ($value < 0 || $value > $max) {
            throw InvalidCondition::because(\sprintf('The condition %s has a value of 1 to %d, or 0 to remove it.', $target->condition, $max));
        }

        if (0 === $value) {
            return $this->remove($character, $instance);
        }

        if ($value === $target->value) {
            return $before;
        }

        $active = array_map(static fn (ActiveCondition $one): ActiveCondition => $one->id === $target->id ? $one->withValue($value) : $one, $before->active);

        return $this->keep($before->with($active, $before->issued, $before->lastTick), [new ConditionChanged($before->character, $target->id, $target->condition)]);
    }

    /**
     * Counts the start of a character's turn for the participants: the durations in rounds counted there run down, and
     * the values that decay there fall.
     *
     * @param string       $tick         an identifier of this boundary: told again right after, it counts once
     * @param list<string> $participants the characters whose conditions count, the one whose turn it is among them or not
     *
     * @return list<CharacterConditions> the participants' conditions, in the order given
     *
     * @throws InvalidCondition  when an identifier is not one, or there are too many participants
     * @throws ConditionConflict when a participant's conditions changed since they were read; those before are kept
     */
    public function startTurn(string $tick, string $character, array $participants): array
    {
        return $this->count($tick, $participants, Boundary::Start, Identifier::check($character, 'a character'));
    }

    /**
     * Counts the end of a character's turn for the participants, as `startTurn()` counts its start.
     *
     * @param list<string> $participants
     *
     * @return list<CharacterConditions>
     *
     * @throws InvalidCondition  when an identifier is not one, or there are too many participants
     * @throws ConditionConflict when a participant's conditions changed since they were read; those before are kept
     */
    public function endTurn(string $tick, string $character, array $participants): array
    {
        return $this->count($tick, $participants, Boundary::End, Identifier::check($character, 'a character'));
    }

    /**
     * Counts the start of a round for the participants: the durations in rounds anchored on the round run down.
     *
     * @param list<string> $participants
     *
     * @return list<CharacterConditions>
     *
     * @throws InvalidCondition  when an identifier is not one, or there are too many participants
     * @throws ConditionConflict when a participant's conditions changed since they were read; those before are kept
     */
    public function startRound(string $tick, array $participants): array
    {
        return $this->count($tick, $participants, Boundary::Start, null);
    }

    /**
     * Counts the end of a round for the participants, as `startRound()` counts its start.
     *
     * @param list<string> $participants
     *
     * @return list<CharacterConditions>
     *
     * @throws InvalidCondition  when an identifier is not one, or there are too many participants
     * @throws ConditionConflict when a participant's conditions changed since they were read; those before are kept
     */
    public function endRound(string $tick, array $participants): array
    {
        return $this->count($tick, $participants, Boundary::End, null);
    }

    /**
     * Advances the time of the fiction for the participants: the durations in game time run down by the seconds.
     *
     * @param list<string> $participants
     *
     * @return list<CharacterConditions>
     *
     * @throws InvalidCondition  when an identifier is not one, the seconds are out of 1 to a hundred years, or there are
     *                           too many participants
     * @throws ConditionConflict when a participant's conditions changed since they were read; those before are kept
     */
    public function advanceTime(string $tick, int $seconds, array $participants): array
    {
        if ($seconds < 1 || $seconds > Duration::MAX_GAME_SECONDS) {
            throw InvalidCondition::because(\sprintf('The time advances by 1 to %d seconds.', Duration::MAX_GAME_SECONDS));
        }

        return $this->count($tick, $participants, null, null, $seconds);
    }

    /**
     * Ends the conditions past their end in real time, for an application that calls it at `ConditionsView::$nextExpiry`.
     * Called early or twice, it changes nothing.
     *
     * @throws InvalidCondition  when the identifier is not one
     * @throws ConditionConflict when the conditions changed since they were read
     */
    public function expire(string $character): CharacterConditions
    {
        $before = $this->conditions($character);
        $events = [];
        $active = $this->pruned($before, Millis::of($this->clock->now()), $events);

        return [] === $events ? $before : $this->keep($before->with($active, $before->issued, $before->lastTick), $events);
    }

    /**
     * The character's conditions to show the table: those past their end in real time left out, even before
     * `expire()` ends them.
     *
     * @throws InvalidCondition when the identifier is not one
     */
    public function view(string $character): ConditionsView
    {
        $conditions = $this->conditions($character);
        $active = $this->current($conditions);
        $states = [];
        $ends = [];
        foreach ($active as $one) {
            $states[] = new ConditionState($one->condition, $one->id, $one->value, $one->source, $one->duration, $one->expiresAt);
            if ($one->expiresAt instanceof DateTimeImmutable) {
                $ends[] = Millis::of($one->expiresAt);
            }
        }

        foreach ($this->implied($active) as $implied => $by) {
            $states[] = new ConditionState($implied, null, null, null, null, null, $by);
        }

        return new ConditionsView($conditions->character, $states, [] === $ends ? null : Millis::at(min($ends)), $conditions->version);
    }

    /**
     * Whether the character has the condition, applied or brought by another, and not past its end in real time.
     *
     * @throws InvalidCondition when an identifier is not one
     */
    public function has(string $character, string $condition): bool
    {
        $active = $this->current($this->conditions($character));
        foreach ($active as $one) {
            if ($one->condition === $condition) {
                return true;
            }
        }

        return isset($this->implied($active)[$condition]);
    }

    /**
     * The modifiers in force on the character: those of each application, by its value, in the order applied, then
     * those of the conditions they bring, once each. A condition the catalogue no longer has modifies nothing.
     *
     * @return list<AppliedModifier>
     *
     * @throws InvalidCondition when the identifier is not one
     */
    public function modifiers(string $character): array
    {
        $active = $this->current($this->conditions($character));
        $modifiers = [];
        foreach ($active as $one) {
            foreach ($this->catalogue->find($one->condition)->modifiers ?? [] as $modifier) {
                $modifiers[] = new AppliedModifier($modifier, $one->condition, $one->id, $modifier->amountFor($one->value));
            }
        }

        foreach (array_keys($this->implied($active)) as $implied) {
            foreach ($this->catalogue->get($implied)->modifiers as $modifier) {
                $modifiers[] = new AppliedModifier($modifier, $implied, null, $modifier->amountFor(null));
            }
        }

        return $modifiers;
    }

    /**
     * The character's statistics once the conditions are counted.
     *
     * @param array<string, int> $base the statistics before the conditions, by identifier
     *
     * @return array<string, StatValue>
     *
     * @throws InvalidCondition when an identifier is not one, or the base is out of its bounds
     */
    public function effective(string $character, array $base): array
    {
        return $this->calculator->compute($base, $this->modifiers($character));
    }

    /**
     * The duration of an application once its value, source and duration are checked against the condition.
     *
     * @throws InvalidCondition when the source is not an identifier, the value is missing, given or out of its bounds,
     *                          or the duration counts the turns of a source not given
     */
    private function checked(ConditionDefinition $definition, ?int $value, ?Duration $duration, ?string $source): Duration
    {
        if (null !== $source) {
            Identifier::check($source, 'a source');
        }

        $max = $definition->maxValue;
        if (null === $max ? null !== $value : null === $value || $value < 1 || $value > $max) {
            throw InvalidCondition::because(null === $max ? \sprintf('The condition %s has no value.', $definition->id) : \sprintf('The condition %s has a value of 1 to %d.', $definition->id, $max));
        }

        $duration ??= $definition->duration ?? Duration::untilRemoved();
        if (DurationKind::Rounds === $duration->kind && Anchor::Source === $duration->anchor && null === $source) {
            throw InvalidCondition::because('A duration counted on the turns of the source needs the source.');
        }

        return $duration;
    }

    /**
     * @param list<string> $participants
     *
     * @return list<CharacterConditions>
     */
    private function count(string $tick, array $participants, ?Boundary $boundary, ?string $turnOf, int $seconds = 0): array
    {
        Identifier::check($tick, 'a tick');
        if (\count($participants) > self::MAX_PARTICIPANTS) {
            throw InvalidCondition::because(\sprintf('A turn counts for %d characters at most.', self::MAX_PARTICIPANTS));
        }

        foreach ($participants as $participant) {
            Identifier::check($participant, 'a character');
        }

        $now = Millis::of($this->clock->now());
        $results = [];
        foreach (array_unique($participants) as $participant) {
            $before = $this->conditions($participant);
            if ($tick === $before->lastTick) {
                $results[] = $before;

                continue;
            }

            $events = [];
            $kept = [];
            $changed = false;
            foreach ($this->pruned($before, $now, $events) as $one) {
                $next = $this->counted($one, $participant, $boundary, $turnOf, $seconds, $events);
                $changed = $changed || $next !== $one;
                if ($next instanceof ActiveCondition) {
                    $kept[] = $next;
                }
            }

            $results[] = $changed || [] !== $events ? $this->keep($before->with($kept, $before->issued, $tick), $events) : $before;
        }

        return $results;
    }

    /**
     * One application once a boundary or a time advance is counted: its value decayed and its duration run down, or
     * null when it ends.
     *
     * @param list<ConditionEvent> $events
     */
    private function counted(ActiveCondition $one, string $character, ?Boundary $boundary, ?string $turnOf, int $seconds, array &$events): ?ActiveCondition
    {
        if (!$boundary instanceof Boundary) {
            if (DurationKind::GameTime !== $one->duration->kind) {
                return $one;
            }

            $left = $one->duration->amount - $seconds;
            if ($left <= 0) {
                $events[] = new ConditionEnded($character, $one->id, $one->condition, EndReason::Expired);

                return null;
            }

            return $one->withDuration($one->duration->withAmount($left));
        }

        $decay = $this->catalogue->find($one->condition)?->decay;
        if ($decay instanceof Decay && $turnOf === $character && $decay->boundary === $boundary) {
            $value = (int) $one->value - $decay->amount;
            if ($value <= 0) {
                $events[] = new ConditionEnded($character, $one->id, $one->condition, EndReason::Decayed);

                return null;
            }

            $one = $one->withValue($value);
            $events[] = new ConditionChanged($character, $one->id, $one->condition);
        }

        if ($one->duration->countsAt($boundary, $turnOf, $character, $one->source)) {
            if (1 === $one->duration->amount) {
                $events[] = new ConditionEnded($character, $one->id, $one->condition, EndReason::Expired);

                return null;
            }

            $one = $one->withDuration($one->duration->withAmount($one->duration->amount - 1));
        }

        return $one;
    }

    /**
     * The applications not past their end in real time at a moment; an event for each one that is.
     *
     * @param list<ConditionEvent> $events
     *
     * @return list<ActiveCondition>
     */
    private function pruned(CharacterConditions $conditions, int $now, array &$events): array
    {
        $kept = [];
        foreach ($conditions->active as $one) {
            if ($one->expiresAt instanceof DateTimeImmutable && Millis::of($one->expiresAt) <= $now) {
                $events[] = new ConditionEnded($conditions->character, $one->id, $one->condition, EndReason::Expired);
            } else {
                $kept[] = $one;
            }
        }

        return $kept;
    }

    /**
     * @return list<ActiveCondition>
     */
    private function current(CharacterConditions $conditions): array
    {
        $ignored = [];

        return $this->pruned($conditions, Millis::of($this->clock->now()), $ignored);
    }

    /**
     * The conditions the applications bring and that are not applied themselves, each with the applied conditions that
     * bring it, in the order met.
     *
     * @param list<ActiveCondition> $active
     *
     * @return array<string, list<string>>
     */
    private function implied(array $active): array
    {
        $applied = array_unique(array_map(static fn (ActiveCondition $one): string => $one->condition, $active));
        $implied = [];
        foreach ($applied as $condition) {
            foreach ($this->catalogue->implied([$condition]) as $brought) {
                if (!\in_array($brought, $applied, true)) {
                    $implied[$brought][] = $condition;
                }
            }
        }

        return $implied;
    }

    /**
     * Keeps the conditions, then tells the events.
     *
     * @param list<ConditionEvent> $events
     */
    private function keep(CharacterConditions $conditions, array $events): CharacterConditions
    {
        $kept = $this->store->save($conditions);
        foreach ($events as $event) {
            $this->observer?->notify($event);
        }

        return $kept;
    }
}
