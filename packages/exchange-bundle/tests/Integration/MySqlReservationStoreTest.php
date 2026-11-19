<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\Exchange\Reservation\ReservationStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalReservationStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\Rows;
use Tilttek\ExchangeBundle\Tests\Unit\Infrastructure\ReservationStoreTestCase;

/**
 * The contract of a store of reservations on a real MySQL server.
 */
#[CoversClass(DbalReservationStore::class)]
#[CoversClass(ExchangeTables::class)]
#[CoversClass(Rows::class)]
#[Group('mysql')]
final class MySqlReservationStoreTest extends ReservationStoreTestCase
{
    use OnMySql;
    use ServerStores;

    #[Override]
    protected function store(): ReservationStore
    {
        return new DbalReservationStore($this->server());
    }
}
