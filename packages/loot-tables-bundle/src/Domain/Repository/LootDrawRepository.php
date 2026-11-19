<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Domain\Repository;

use Tilttek\LootTablesBundle\Domain\Model\LootDraw;

/**
 * The draws of the orders, committed, revealed or entered.
 */
interface LootDrawRepository
{
    public function get(string $id): ?LootDraw;

    public function save(LootDraw $draw): void;

    /**
     * The draws of an order, by line, option and unit.
     *
     * @return list<LootDraw>
     */
    public function ofOrder(string $orderNumber): array;
}
