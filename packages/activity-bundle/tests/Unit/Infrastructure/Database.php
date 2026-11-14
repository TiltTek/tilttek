<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use LogicException;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\ActivityTables;

/**
 * A SQLite database in memory with the journal's tables.
 */
final class Database
{
    private function __construct()
    {
    }

    /**
     * @template T of Connection
     *
     * @param class-string<T> $wrapper
     *
     * @return T
     */
    public static function connection(string $wrapper = Connection::class): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => $wrapper]);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(ActivityTables::configure(new Schema())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        return $connection instanceof $wrapper ? $connection : throw new LogicException(\sprintf('The connection is not a %s.', $wrapper));
    }
}
