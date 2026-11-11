<?php

declare(strict_types=1);

namespace Tilttek\Collections\Dice;

use Override;
use Phil\Resource\ResourceReference;

/**
 * The dice the members chose, in memory.
 */
final class InMemoryChoiceStore implements ChoiceStore
{
    /**
     * @var array<string, array<int, DieChoice>> by member, then by number of sides
     */
    private array $choices = [];

    #[Override]
    public function save(DieChoice $choice): void
    {
        $this->choices[$choice->member->toString()][$choice->sides] = $choice;
    }

    #[Override]
    public function find(ResourceReference $member, int $sides): ?DieChoice
    {
        return $this->choices[$member->toString()][$sides] ?? null;
    }

    #[Override]
    public function remove(ResourceReference $member, int $sides): bool
    {
        $there = isset($this->choices[$member->toString()][$sides]);
        unset($this->choices[$member->toString()][$sides]);

        return $there;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        $choices = $this->choices[$member->toString()] ?? [];
        usort($choices, static fn (DieChoice $a, DieChoice $b): int => $a->sides <=> $b->sides);

        return $choices;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $erased = \count($this->choices[$member->toString()] ?? []);
        unset($this->choices[$member->toString()]);

        return $erased;
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->choices = [];
    }
}
