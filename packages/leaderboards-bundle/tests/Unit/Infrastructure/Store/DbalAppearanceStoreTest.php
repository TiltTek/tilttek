<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Leaderboards\Store\AppearanceStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalAppearanceStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalAppearanceStore::class)]
#[CoversClass(Rows::class)]
final class DbalAppearanceStoreTest extends AppearanceStoreTestCase
{
    protected function store(): AppearanceStore
    {
        return new DbalAppearanceStore(Database::connection());
    }
}
