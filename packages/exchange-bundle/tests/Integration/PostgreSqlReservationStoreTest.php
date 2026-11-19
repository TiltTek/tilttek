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
 * The contract of a store of reservations on a real PostgreSQL server.
 */
#[CoversClass(DbalReservationStore::class)]
#[CoversClass(ExchangeTables::class)]
#[CoversClass(Rows::class)]
#[Group('postgresql')]
final class PostgreSqlReservationStoreTest extends ReservationStoreTestCase
{
    use OnPostgreSql;
    use ServerStores;

    #[Override]
    protected function store(): ReservationStore
    {
        return new DbalReservationStore($this->server());
    }
}
