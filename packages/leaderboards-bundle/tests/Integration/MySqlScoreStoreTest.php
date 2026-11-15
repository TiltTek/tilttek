<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\Leaderboards\Store\ScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store\ScoreStoreTestCase;

/**
 * The contract of a score store on a real MySQL server.
 */
#[CoversClass(DbalScoreStore::class)]
#[CoversClass(LeaderboardsTables::class)]
#[CoversClass(Rows::class)]
#[Group('mysql')]
final class MySqlScoreStoreTest extends ScoreStoreTestCase
{
    use OnMySql;
    use ServerStores;

    #[Override]
    protected function store(): ScoreStore
    {
        return new DbalScoreStore($this->server());
    }
}
