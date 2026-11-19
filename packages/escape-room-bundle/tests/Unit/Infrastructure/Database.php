<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomTables;

/**
 * A SQLite database in memory with the tables of the escape rooms.
 */
final class Database
{
    private function __construct()
    {
    }

    public static function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(EscapeRoomTables::configure(Schema::editor()->create())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        return $connection;
    }
}
