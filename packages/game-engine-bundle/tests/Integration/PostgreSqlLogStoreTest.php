<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\DbalLogStore;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineTables;
use Tilttek\GameEngineBundle\Tests\Unit\Infrastructure\LogStoreTestCase;

/**
 * The log store against a real server, read from `TILTTEK_GAME_ENGINE_TEST_POSTGRESQL_URL`, else from
 * `PHIL_TEST_POSTGRESQL_URL`, which `phil-dev services env` sets; skipped without either.
 */
#[CoversClass(DbalLogStore::class)]
#[CoversClass(GameEngineTables::class)]
#[Group('postgresql')]
final class PostgreSqlLogStoreTest extends LogStoreTestCase
{
    #[Override]
    protected function connection(): Connection
    {
        foreach (['TILTTEK_GAME_ENGINE_TEST_POSTGRESQL_URL', 'PHIL_TEST_POSTGRESQL_URL'] as $variable) {
            $url = getenv($variable);

            if (\is_string($url) && '' !== $url) {
                return DriverManager::getConnection(new DsnParser(['mysql' => 'pdo_mysql', 'postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
            }
        }

        self::markTestSkipped('Set PHIL_TEST_POSTGRESQL_URL, or run phil-dev services, to test against a real server.');
    }
}
