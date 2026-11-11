<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Progression\Store\StreakStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\DbalStreakStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\Rows;
use Tilttek\ProgressionBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalStreakStore::class)]
#[CoversClass(Rows::class)]
final class DbalStreakStoreTest extends StreakStoreTestCase
{
    protected function store(): StreakStore
    {
        return new DbalStreakStore(Database::connection());
    }
}
