<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\Leaderboards\Store\SnapshotStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalSnapshotStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store\SnapshotStoreTestCase;

/**
 * The contract of a snapshot store on a real PostgreSQL server.
 */
#[CoversClass(DbalSnapshotStore::class)]
#[CoversClass(LeaderboardsTables::class)]
#[CoversClass(Rows::class)]
#[Group('postgresql')]
final class PostgreSqlSnapshotStoreTest extends SnapshotStoreTestCase
{
    use OnPostgreSql;
    use ServerStores;

    #[Override]
    protected function store(): SnapshotStore
    {
        return new DbalSnapshotStore($this->server());
    }
}
