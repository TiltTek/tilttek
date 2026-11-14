<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use RuntimeException;
use Tilttek\Stickers\Contract\PlacementStore;
use Tilttek\Stickers\Placement;
use Tilttek\Stickers\Store\InMemoryPlacementStore;

/**
 * A placement store of tests whose removals fail once a number of them went through, as a database that goes away
 * in the middle of a revocation.
 */
final class FailingPlacementStore implements PlacementStore
{
    public ?int $removalsBeforeFailure = null;

    public function __construct(
        private readonly InMemoryPlacementStore $store = new InMemoryPlacementStore(),
    ) {
    }

    #[Override]
    public function add(Placement $placement): void
    {
        $this->store->add($placement);
    }

    #[Override]
    public function replace(Placement $placement): void
    {
        $this->store->replace($placement);
    }

    #[Override]
    public function get(string $id): ?Placement
    {
        return $this->store->get($id);
    }

    #[Override]
    public function remove(string $id): bool
    {
        if (0 === $this->removalsBeforeFailure) {
            throw new RuntimeException('The store went away.');
        }

        if (null !== $this->removalsBeforeFailure) {
            --$this->removalsBeforeFailure;
        }

        return $this->store->remove($id);
    }

    #[Override]
    public function onSurface(ResourceReference $surface): array
    {
        return $this->store->onSurface($surface);
    }

    #[Override]
    public function ofHolder(ResourceReference $holder): array
    {
        return $this->store->ofHolder($holder);
    }

    #[Override]
    public function eraseHolder(ResourceReference $holder): int
    {
        return $this->store->eraseHolder($holder);
    }

    #[Override]
    public function eraseSurface(ResourceReference $surface): int
    {
        return $this->store->eraseSurface($surface);
    }
}
