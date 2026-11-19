<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Fixture;

use Override;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;

/**
 * The orders of the tests, in memory.
 */
final class Orders implements OrderRepository
{
    /** @var array<string, Order> */
    private array $orders = [];

    public function __construct(Order ...$orders)
    {
        foreach ($orders as $order) {
            $this->add($order);
        }
    }

    #[Override]
    public function find(string $number): ?Order
    {
        return $this->orders[$number] ?? null;
    }

    #[Override]
    public function add(Order $order): void
    {
        $this->orders[$order->number] = $order;
    }
}
