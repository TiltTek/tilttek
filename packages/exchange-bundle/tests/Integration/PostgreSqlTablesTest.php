<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalCatalog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalReservationStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalTransferLog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\Rows;

/**
 * The tables, the moments, the catalogue and the insertion written once on a real PostgreSQL server.
 */
#[CoversClass(ExchangeTables::class)]
#[CoversClass(DbalCatalog::class)]
#[CoversClass(DbalReservationStore::class)]
#[CoversClass(DbalTransferLog::class)]
#[CoversClass(Rows::class)]
#[Group('postgresql')]
final class PostgreSqlTablesTest extends ServerTablesTestCase
{
    use OnPostgreSql;
    use ServerStores;
}
