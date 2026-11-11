<?php

declare(strict_types=1);

namespace Tilttek\Collections\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\SetCompletion;

/**
 * The sets completed in memory.
 */
final class InMemoryCompletionStore implements CompletionStore
{
    /**
     * @var array<string, array<string, SetCompletion>> by member, then by set
     */
    private array $completions = [];

    #[Override]
    public function add(SetCompletion $completion): bool
    {
        if (isset($this->completions[$completion->member->toString()][$completion->set])) {
            return false;
        }

        $this->completions[$completion->member->toString()][$completion->set] = $completion;

        return true;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        $completions = $this->completions[$member->toString()] ?? [];
        usort($completions, static fn (SetCompletion $a, SetCompletion $b): int => [$a->completedAt->getTimestamp(), $a->set] <=> [$b->completedAt->getTimestamp(), $b->set]);

        return $completions;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $erased = \count($this->completions[$member->toString()] ?? []);
        unset($this->completions[$member->toString()]);

        return $erased;
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->completions = [];
    }
}
