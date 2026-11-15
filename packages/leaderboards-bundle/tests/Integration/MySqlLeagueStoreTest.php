<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\Leaderboards\Store\LeagueStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalLeagueStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store\LeagueStoreTestCase;

/**
 * The contract of a league store on a real MySQL server.
 */
#[CoversClass(DbalLeagueStore::class)]
#[CoversClass(LeaderboardsTables::class)]
#[CoversClass(Rows::class)]
#[Group('mysql')]
final class MySqlLeagueStoreTest extends LeagueStoreTestCase
{
    use OnMySql;
    use ServerStores;

    #[Override]
    protected function store(): LeagueStore
    {
        return new DbalLeagueStore($this->server());
    }
}
