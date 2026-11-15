<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Leaderboards\Store\SnapshotStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalSnapshotStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalSnapshotStore::class)]
#[CoversClass(Rows::class)]
final class DbalSnapshotStoreTest extends SnapshotStoreTestCase
{
    protected function store(): SnapshotStore
    {
        return new DbalSnapshotStore(Database::connection());
    }
}
