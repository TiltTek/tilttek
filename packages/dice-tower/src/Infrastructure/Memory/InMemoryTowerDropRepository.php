<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceTower\Domain\Contract\TowerDropRepository;
use Tilttek\DiceTower\Domain\Model\TowerDrop;

/**
 * The log of the towers in memory, for tests; it is emptied between two requests of a long-running process.
 */
final class InMemoryTowerDropRepository implements TowerDropRepository
{
    /**
     * @var array<int, TowerDrop>
     */
    private array $drops = [];

    private int $last = 0;

    #[Override]
    public function append(ResourceReference $table, string $roll, ?ResourceReference $roller, DateTimeImmutable $at): TowerDrop
    {
        $drop = TowerDrop::drop($table, $roll, $roller, $at, ++$this->last);
        $this->drops[] = $drop;

        return $drop;
    }

    #[Override]
    public function after(ResourceReference $table, int $position, int $limit): array
    {
        $drops = array_filter($this->of($table), static fn (TowerDrop $drop): bool => $drop->position() > $position);

        return \array_slice($drops, 0, $limit);
    }

    #[Override]
    public function latest(ResourceReference $table, int $limit): array
    {
        return \array_slice($this->of($table), -$limit);
    }

    #[Override]
    public function at(ResourceReference $table, int $position): ?TowerDrop
    {
        foreach ($this->of($table) as $drop) {
            if ($drop->position() === $position) {
                return $drop;
            }
        }

        return null;
    }

    #[Override]
    public function eraseRoller(ResourceReference $roller): int
    {
        $kept = array_filter($this->drops, static fn (TowerDrop $drop): bool => true !== $drop->roller()?->equals($roller));
        $erased = \count($this->drops) - \count($kept);
        $this->drops = $kept;

        return $erased;
    }

    /**
     * Empties the log, so that each request of a long-running process starts as a new process would.
     */
    public function reset(): void
    {
        $this->drops = [];
        $this->last = 0;
    }

    /**
     * @return array<int, TowerDrop>
     */
    private function of(ResourceReference $table): array
    {
        return array_filter($this->drops, static fn (TowerDrop $drop): bool => $drop->table()->equals($table));
    }
}
