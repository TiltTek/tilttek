<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalSnapshotStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;

/**
 * The tables, the moments and the insertion written once on a real PostgreSQL server.
 */
#[CoversClass(LeaderboardsTables::class)]
#[CoversClass(DbalScoreStore::class)]
#[CoversClass(DbalSnapshotStore::class)]
#[CoversClass(Rows::class)]
#[Group('postgresql')]
final class PostgreSqlTablesTest extends ServerTablesTestCase
{
    use OnPostgreSql;
    use ServerStores;
}
