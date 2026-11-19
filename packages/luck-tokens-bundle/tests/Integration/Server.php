<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Integration;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensTables;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Interloper;

/**
 * A connection to the real server of a URL, with the tokens' tables dropped and created again once per process, then
 * emptied before each test.
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
     * A connection, through the interloper, with the tokens' tables there and empty.
     */
    public static function connect(string $url, Interloper $interloper = new Interloper()): Connection
    {
        $connection = self::open($url, $interloper);
        $platform = $connection->getDatabasePlatform();
        $tables = LuckTokensTables::tables($platform);

        if (!isset(self::$created[$url])) {
            foreach ($tables as $table) {
                $connection->executeStatement('DROP TABLE IF EXISTS '.$table->getObjectName()->toSQL($platform));
            }

            foreach ($platform->getCreateTablesSQL(array_values($tables)) as $sql) {
                $connection->executeStatement($sql);
            }

            self::$created[$url] = true;
        }

        foreach ($tables as $table) {
            $connection->executeStatement('DELETE FROM '.$table->getObjectName()->toSQL($platform));
        }

        return $connection;
    }

    /**
     * Another connection to the server, as another process would open.
     */
    public static function open(string $url, Interloper $interloper = new Interloper()): Connection
    {
        return DriverManager::getConnection(
            new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url),
            new Configuration()->setMiddlewares([$interloper]),
        );
    }
}
