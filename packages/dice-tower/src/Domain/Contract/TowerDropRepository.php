<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Domain\Contract;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\DiceTower\Domain\Model\TowerDrop;

/**
 * The log of the towers: where each roll fell, in order. Every read comes from the store, so that a process serving
 * one request after another keeps nothing between them.
 */
interface TowerDropRepository
{
    /**
     * Keeps a drop and gives it its position, after every drop kept before it.
     */
    public function append(ResourceReference $table, string $roll, ?ResourceReference $roller, DateTimeImmutable $at): TowerDrop;

    /**
     * The drops of a table after a position, the earliest first.
     *
     * @return list<TowerDrop>
     */
    public function after(ResourceReference $table, int $position, int $limit): array;

    /**
     * The last drops of a table, the earliest first.
     *
     * @return list<TowerDrop>
     */
    public function latest(ResourceReference $table, int $limit): array;

    public function at(ResourceReference $table, int $position): ?TowerDrop;

    /**
     * Erases every drop of a roller, when the person is erased.
     *
     * @return int how many drops were erased
     */
    public function eraseRoller(ResourceReference $roller): int;
}
