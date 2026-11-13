<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Stickers\Contract\PlacementStore;
use Tilttek\Stickers\Placement;

/**
 * The placements in memory, for tests and for an application that keeps them for one process.
 */
final class InMemoryPlacementStore implements PlacementStore
{
    /** @var array<string, Placement> */
    private array $placements = [];

    #[Override]
    public function add(Placement $placement): void
    {
        $this->placements[$placement->id] = $placement;
    }

    #[Override]
    public function replace(Placement $placement): void
    {
        $this->placements[$placement->id] = $placement;
    }

    #[Override]
    public function get(string $id): ?Placement
    {
        return $this->placements[$id] ?? null;
    }

    #[Override]
    public function remove(string $id): bool
    {
        if (!isset($this->placements[$id])) {
            return false;
        }

        unset($this->placements[$id]);

        return true;
    }

    #[Override]
    public function onSurface(ResourceReference $surface): array
    {
        return array_values(array_filter($this->placements, static fn (Placement $placement): bool => $placement->surface->equals($surface)));
    }

    #[Override]
    public function ofHolder(ResourceReference $holder): array
    {
        return array_values(array_filter($this->placements, static fn (Placement $placement): bool => $placement->holder->equals($holder)));
    }

    #[Override]
    public function eraseHolder(ResourceReference $holder): int
    {
        return $this->eraseWhere(static fn (Placement $placement): bool => $placement->holder->equals($holder));
    }

    #[Override]
    public function eraseSurface(ResourceReference $surface): int
    {
        return $this->eraseWhere(static fn (Placement $placement): bool => $placement->surface->equals($surface));
    }

    /**
     * @param callable(Placement): bool $erased
     */
    private function eraseWhere(callable $erased): int
    {
        $before = \count($this->placements);
        $this->placements = array_filter($this->placements, static fn (Placement $placement): bool => !$erased($placement));

        return $before - \count($this->placements);
    }
}
