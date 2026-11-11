<?php

declare(strict_types=1);

namespace Tilttek\Collections\Pack;

use Override;
use Phil\Resource\ResourceReference;

/**
 * The packs opened, in memory.
 */
final class InMemoryOpeningStore implements OpeningStore
{
    /**
     * @var array<string, PackOpening>
     */
    private array $openings = [];

    #[Override]
    public function add(PackOpening $opening): bool
    {
        if (isset($this->openings[$opening->key])) {
            return false;
        }

        $this->openings[$opening->key] = $opening;

        return true;
    }

    #[Override]
    public function find(string $key): ?PackOpening
    {
        return $this->openings[$key] ?? null;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        $openings = array_filter($this->openings, static fn (PackOpening $opening): bool => $opening->member->equals($member));
        usort($openings, static fn (PackOpening $a, PackOpening $b): int => [$a->openedAt->getTimestamp(), $a->key] <=> [$b->openedAt->getTimestamp(), $b->key]);

        return $openings;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $erased = array_filter($this->openings, static fn (PackOpening $opening): bool => $opening->member->equals($member));
        $this->openings = array_diff_key($this->openings, $erased);

        return \count($erased);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->openings = [];
    }
}
