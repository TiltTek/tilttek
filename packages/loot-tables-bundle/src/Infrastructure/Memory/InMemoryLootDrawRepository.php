<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Infrastructure\Memory;

use Override;
use Symfony\Contracts\Service\ResetInterface;
use Tilttek\LootTablesBundle\Domain\Model\LootDraw;
use Tilttek\LootTablesBundle\Domain\Repository\LootDrawRepository;

/**
 * The draws in memory, for tests: emptied when the kernel resets between requests.
 */
final class InMemoryLootDrawRepository implements LootDrawRepository, ResetInterface
{
    /**
     * @var array<string, LootDraw>
     */
    private array $draws = [];

    #[Override]
    public function get(string $id): ?LootDraw
    {
        return $this->draws[$id] ?? null;
    }

    #[Override]
    public function save(LootDraw $draw): void
    {
        $this->draws[$draw->id()] = $draw;
    }

    #[Override]
    public function ofOrder(string $orderNumber): array
    {
        $draws = array_filter($this->draws, static fn (LootDraw $draw): bool => $draw->orderNumber() === $orderNumber);
        usort($draws, static fn (LootDraw $left, LootDraw $right): int => [$left->line(), $left->option(), $left->unit()] <=> [$right->line(), $right->option(), $right->unit()]);

        return $draws;
    }

    #[Override]
    public function reset(): void
    {
        $this->draws = [];
    }
}
