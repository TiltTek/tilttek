<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\Leaderboards\Store\AppearanceStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalAppearanceStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store\AppearanceStoreTestCase;

/**
 * The contract of a appearance store on a real PostgreSQL server.
 */
#[CoversClass(DbalAppearanceStore::class)]
#[CoversClass(LeaderboardsTables::class)]
#[CoversClass(Rows::class)]
#[Group('postgresql')]
final class PostgreSqlAppearanceStoreTest extends AppearanceStoreTestCase
{
    use OnPostgreSql;
    use ServerStores;

    #[Override]
    protected function store(): AppearanceStore
    {
        return new DbalAppearanceStore($this->server());
    }
}
