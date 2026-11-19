<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;

/**
 * A connection to the real server of a URL, with the exchanges' tables dropped and created again once per process,
 * then emptied before each test.
 */
final class Server
{
    /**
     * The URLs whose tables this process created.
     *
     * @var array<string, true>
     */
    private static array $created = [];

    private function __construct()
    {
    }

    /**
     * The URL of the first variable set and not empty, or null.
     */
    public static function url(string ...$variables): ?string
    {
        foreach ($variables as $variable) {
            $url = getenv($variable);

            if (\is_string($url) && '' !== $url) {
                return $url;
            }
        }

        return null;
    }

    /**
     * A connection with the exchanges' tables there and empty.
     */
    public static function connect(string $url): Connection
    {
        $connection = DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        $tables = ExchangeTables::configure(Schema::editor()->create())->getTables();

        if (!isset(self::$created[$url])) {
            foreach ($tables as $table) {
                $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getObjectName()->toSQL($connection->getDatabasePlatform()));
            }

            foreach ($connection->getDatabasePlatform()->getCreateTablesSQL($tables) as $sql) {
                $connection->executeStatement($sql);
            }

            self::$created[$url] = true;
        }

        foreach ($tables as $table) {
            $connection->executeStatement('DELETE FROM '.$table->getObjectName()->toSQL($connection->getDatabasePlatform()));
        }

        return $connection;
    }
}
