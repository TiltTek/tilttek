<?php

declare(strict_types=1);

namespace Tilttek\Collections;

/**
 * How far a member is in a set: the items of it they own, out of all its items — *12 of 20* —, those missing, and
 * whether they completed it once.
 */
final readonly class SetProgress
{
    /**
     * @param list<Item> $owned
     * @param list<Item> $missing
     */
    public function __construct(
        public ItemSet $set,
        public array $owned,
        public array $missing,
        public bool $completedOnce,
    ) {
    }

    public function total(): int
    {
        return \count($this->owned) + \count($this->missing);
    }

    /**
     * Whether the member owns every item of the set; a set that holds no item yet is never complete.
     */
    public function isComplete(): bool
    {
        return [] === $this->missing && [] !== $this->owned;
    }
}
