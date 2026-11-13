<?php

declare(strict_types=1);

namespace Tilttek\Sets;

/**
 * A set measured against holdings, with nothing recorded: the members owned and missing in the set's order — *12 of
 * 20* —, the percentage, and the tiers reached.
 */
final readonly class Evaluation
{
    /**
     * @param list<string> $owned
     * @param list<string> $missing
     * @param list<Tier>   $tiersReached
     */
    public function __construct(
        public SetDefinition $set,
        public array $owned,
        public array $missing,
        public array $tiersReached,
    ) {
    }

    public function total(): int
    {
        return \count($this->owned) + \count($this->missing);
    }

    public function ownedCount(): int
    {
        return \count($this->owned);
    }

    /**
     * The share of the members owned, rounded down: 100 only when the set is complete.
     */
    public function percent(): int
    {
        return intdiv(100 * \count($this->owned), $this->total());
    }

    /**
     * Whether the holder owns every member of the set, at any level.
     */
    public function isComplete(): bool
    {
        return [] === $this->missing;
    }

    public function hasReached(string $tier): bool
    {
        return array_any($this->tiersReached, static fn (Tier $reached): bool => $reached->code === $tier);
    }

    /**
     * The first tier of the set not reached yet, or null when every tier is.
     */
    public function nextTier(): ?Tier
    {
        return $this->set->tiers[\count($this->tiersReached)] ?? null;
    }
}
