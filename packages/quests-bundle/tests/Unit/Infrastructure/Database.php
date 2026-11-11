<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\QuestsTables;

/**
 * A SQLite database in memory with the quests' tables.
 */
final class Database
{
    private function __construct()
    {
    }

    public static function connection(): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(QuestsTables::configure(new Schema())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        return $connection;
    }
}
