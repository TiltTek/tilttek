<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensTables;

/**
 * A SQLite database with the tokens' tables, through an interloper the tests arm: in memory, or in a file in
 * write-ahead logging, where a second connection writes while the first one reads, as another process would.
 */
final class Database
{
    private function __construct()
    {
    }

    public static function connection(Interloper $interloper = new Interloper()): Connection
    {
        return self::create(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([$interloper])));
    }

    /**
     * A database in a new file, and the connection that created it.
     */
    public static function file(string $path, Interloper $interloper = new Interloper()): Connection
    {
        $connection = self::open($path, $interloper);
        $connection->executeQuery('PRAGMA journal_mode = WAL')->free();

        return self::create($connection);
    }

    /**
     * Another connection to the database of a file.
     */
    public static function open(string $path, Interloper $interloper = new Interloper()): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path], new Configuration()->setMiddlewares([$interloper]));
    }

    private static function create(Connection $connection): Connection
    {
        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(array_values(LuckTokensTables::tables($connection->getDatabasePlatform()))) as $sql) {
            $connection->executeStatement($sql);
        }

        return $connection;
    }
}
