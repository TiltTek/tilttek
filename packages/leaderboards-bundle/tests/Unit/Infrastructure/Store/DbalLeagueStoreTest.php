<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Leaderboards\Store\LeagueStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalLeagueStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalLeagueStore::class)]
#[CoversClass(Rows::class)]
final class DbalLeagueStoreTest extends LeagueStoreTestCase
{
    protected function store(): LeagueStore
    {
        return new DbalLeagueStore(Database::connection());
    }
}
