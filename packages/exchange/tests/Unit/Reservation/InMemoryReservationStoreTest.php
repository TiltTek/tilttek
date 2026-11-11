<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Reservation;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionObject;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\ReservationStore;

#[CoversClass(InMemoryReservationStore::class)]
final class InMemoryReservationStoreTest extends ReservationStoreTestCase
{
    #[Override]
    protected function store(): ReservationStore
    {
        return new InMemoryReservationStore();
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryReservationStore();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryReservationStore(), $store);
    }
}
