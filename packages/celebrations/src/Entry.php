<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

use DateTimeImmutable;
use Tilttek\Celebrations\Exception\InvalidCelebration;

/**
 * An achievement in a member's inbox: its sequence — the order the inbox received it in, never reused for that member
 * —, its state, the moment it was received and the moment it was settled — seen, withdrawn or lapsed —, none while it
 * is pending.
 */
final readonly class Entry
{
    /**
     * @throws InvalidCelebration
     */
    public function __construct(
        public Achievement $achievement,
        public int $sequence,
        public EntryState $state,
        public DateTimeImmutable $receivedAt,
        public ?DateTimeImmutable $settledAt = null,
    ) {
        if ($sequence < 1) {
            throw InvalidCelebration::because(\sprintf('The sequence of an entry starts at 1; %d is not one.', $sequence));
        }

        if ((EntryState::Pending === $state) !== (!$settledAt instanceof DateTimeImmutable)) {
            throw InvalidCelebration::because('A pending entry has no settling moment, and a settled entry has one.');
        }
    }

    /**
     * The member's reference, written `kind:identifier`.
     */
    public function member(): string
    {
        return $this->achievement->member->toString();
    }

    /**
     * The entry as it stands at a moment: a pending entry whose moment to be shown passed is lapsed since that moment.
     */
    public function at(DateTimeImmutable $now): self
    {
        $until = $this->achievement->showUntil;

        if (EntryState::Pending !== $this->state || !$until instanceof DateTimeImmutable || $now < $until) {
            return $this;
        }

        return $this->settled(EntryState::Lapsed, $until);
    }

    /**
     * Whether the entry waits to be shown at that moment.
     */
    public function isShowableAt(DateTimeImmutable $now): bool
    {
        return EntryState::Pending === $this->at($now)->state;
    }

    /**
     * The entry settled in another state at a moment.
     */
    public function settled(EntryState $state, DateTimeImmutable $at): self
    {
        if (EntryState::Pending === $state) {
            throw InvalidCelebration::because('An entry is settled as seen, withdrawn or lapsed, never as pending.');
        }

        return new self($this->achievement, $this->sequence, $state, $this->receivedAt, $at);
    }
}
