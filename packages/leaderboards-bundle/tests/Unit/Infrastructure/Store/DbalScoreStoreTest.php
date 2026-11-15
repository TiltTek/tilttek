<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Leaderboards\Store\ScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalScoreStore::class)]
#[CoversClass(Rows::class)]
final class DbalScoreStoreTest extends ScoreStoreTestCase
{
    protected function store(): ScoreStore
    {
        return new DbalScoreStore(Database::connection());
    }
}
