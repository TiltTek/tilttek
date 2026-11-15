<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Sets\Completion;
use Tilttek\Sets\Contract\CompletionStore;
use Tilttek\Sets\Store\InMemoryCompletionStore;

/**
 * The base of the completion stores of tests that hand every call of the port to a store in memory.
 */
abstract class DelegatingCompletionStore implements CompletionStore
{
    public function __construct(
        protected readonly InMemoryCompletionStore $inner = new InMemoryCompletionStore(),
    ) {
    }

    #[Override]
    public function add(Completion $completion): bool
    {
        return $this->inner->add($completion);
    }

    #[Override]
    public function undelivered(ResourceReference $holder): array
    {
        return $this->inner->undelivered($holder);
    }

    #[Override]
    public function markDelivered(Completion $completion): void
    {
        $this->inner->markDelivered($completion);
    }

    #[Override]
    public function of(ResourceReference $holder, string $set): array
    {
        return $this->inner->of($holder, $set);
    }

    #[Override]
    public function ofHolder(ResourceReference $holder): array
    {
        return $this->inner->ofHolder($holder);
    }

    #[Override]
    public function eraseHolder(ResourceReference $holder): int
    {
        return $this->inner->eraseHolder($holder);
    }
}
