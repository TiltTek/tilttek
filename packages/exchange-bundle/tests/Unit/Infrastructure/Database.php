<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;
use Tilttek\PointsBundle\Infrastructure\Doctrine\PointsTables;

/**
 * A SQLite database in memory with the exchanges' tables and the ledger's, as an application keeps them on one
 * connection.
 */
final class Database
{
    private function __construct()
    {
    }

    public static function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(PointsTables::configure(ExchangeTables::configure(Schema::editor()->create()))->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        return $connection;
    }
}
