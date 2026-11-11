<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Store;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Collections\Daily\DailyStore;
use Tilttek\CollectionsBundle\Infrastructure\Doctrine\DbalDailyStore;
use Tilttek\CollectionsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalDailyStore::class)]
final class DbalDailyStoreTest extends DailyStoreTestCase
{
    #[Override]
    protected function store(): DailyStore
    {
        return new DbalDailyStore(Database::connection());
    }
}
