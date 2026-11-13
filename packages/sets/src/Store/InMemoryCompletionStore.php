<?php

declare(strict_types=1);

namespace Tilttek\Sets\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Sets\Completion;
use Tilttek\Sets\Contract\CompletionStore;

/**
 * The completions in memory, for tests and for an application that keeps them for one process.
 */
final class InMemoryCompletionStore implements CompletionStore
{
    /** @var array<string, Completion> */
    private array $completions = [];

    #[Override]
    public function add(Completion $completion): bool
    {
        if (isset($this->completions[$completion->key()])) {
            return false;
        }

        $this->completions[$completion->key()] = $completion;

        return true;
    }

    #[Override]
    public function of(ResourceReference $holder, string $set): array
    {
        return array_values(array_filter(
            $this->completions,
            static fn (Completion $completion): bool => $completion->set === $set && $completion->holder->equals($holder),
        ));
    }

    #[Override]
    public function ofHolder(ResourceReference $holder): array
    {
        return array_values(array_filter(
            $this->completions,
            static fn (Completion $completion): bool => $completion->holder->equals($holder),
        ));
    }

    #[Override]
    public function eraseHolder(ResourceReference $holder): int
    {
        $before = \count($this->completions);
        $this->completions = array_filter(
            $this->completions,
            static fn (Completion $completion): bool => !$completion->holder->equals($holder),
        );

        return $before - \count($this->completions);
    }
}
