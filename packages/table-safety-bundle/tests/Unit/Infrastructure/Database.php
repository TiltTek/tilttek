<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\TableSafetyTables;

/**
 * A SQLite database in memory with the tables of the safety tools, or those tables added to a connection.
 */
final class Database
{
    private function __construct()
    {
    }

    public static function connection(): Connection
    {
        return self::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]));
    }

    public static function create(Connection $connection): Connection
    {
        $manager = $connection->createSchemaManager();
        $schema = TableSafetyTables::configure($manager->introspectSchema());

        foreach ([TableSafetyTables::TABLES, TableSafetyTables::PARTICIPATIONS, TableSafetyTables::SIGNALS, TableSafetyTables::FEEDBACK] as $name) {
            if (!$manager->tablesExist([$name])) {
                $manager->createTable($schema->getTable($name));
            }
        }

        return $connection;
    }
}
