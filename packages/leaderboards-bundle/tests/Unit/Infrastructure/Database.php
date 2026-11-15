<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;

/**
 * A SQLite database in memory with the leaderboards' tables.
 */
final class Database
{
    private function __construct()
    {
    }

    public static function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(LeaderboardsTables::configure(Schema::editor()->create())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        return $connection;
    }
}
