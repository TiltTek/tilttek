<?php

declare(strict_types=1);

namespace Tilttek\Sets\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Sets\Completion;
use Tilttek\Sets\Contract\CompletionStore;
use Tilttek\Sets\Contract\PendingDeliveries;

/**
 * The completions in memory, for tests and for an application that keeps them for one process, with the holders whose
 * completions wait to be delivered.
 */
final class InMemoryCompletionStore implements CompletionStore, PendingDeliveries
{
    /** @var array<string, Completion> */
    private array $completions = [];

    /** @var array<string, Completion> the completions not yet delivered, in the order they were added */
    private array $undelivered = [];

    #[Override]
    public function add(Completion $completion): bool
    {
        if (isset($this->completions[$completion->key()])) {
            return false;
        }

        $this->completions[$completion->key()] = $completion;
        $this->undelivered[$completion->key()] = $completion;

        return true;
    }

    #[Override]
    public function undelivered(ResourceReference $holder): array
    {
        return array_values(array_filter(
            $this->undelivered,
            static fn (Completion $completion): bool => $completion->holder->equals($holder),
        ));
    }

    #[Override]
    public function holdersWithUndelivered(int $limit): array
    {
        if ($limit < 1) {
            return [];
        }

        $holders = [];

        foreach ($this->undelivered as $completion) {
            $holders[$completion->holder->toString()] ??= $completion->holder;

            if (\count($holders) >= $limit) {
                break;
            }
        }

        return array_values($holders);
    }

    #[Override]
    public function markDelivered(Completion $completion): void
    {
        unset($this->undelivered[$completion->key()]);
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
        $this->undelivered = array_intersect_key($this->undelivered, $this->completions);

        return $before - \count($this->completions);
    }
}
