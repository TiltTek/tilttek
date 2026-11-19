<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Fixture;

use Override;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;

/**
 * A store that keeps copies, as a database does: a change made to a draw read from it is lost unless it is saved.
 */
final class CopyingRepository implements LootDrawRepository
{
    /**
     * @var array<string, LootDraw>
     */
    private array $draws = [];

    #[Override]
    public function get(string $id): ?LootDraw
    {
        return isset($this->draws[$id]) ? clone $this->draws[$id] : null;
    }

    #[Override]
    public function save(LootDraw $draw): void
    {
        $this->draws[$draw->id()] = clone $draw;
    }

    #[Override]
    public function ofOrder(string $orderNumber): array
    {
        return array_values(array_map(static fn (LootDraw $draw): LootDraw => clone $draw, array_filter($this->draws, static fn (LootDraw $draw): bool => $draw->orderNumber() === $orderNumber)));
    }
}
