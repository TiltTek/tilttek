<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Progression\Store\MilestoneStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\DbalMilestoneStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\Rows;
use Tilttek\ProgressionBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalMilestoneStore::class)]
#[CoversClass(Rows::class)]
final class DbalMilestoneStoreTest extends MilestoneStoreTestCase
{
    protected function store(): MilestoneStore
    {
        return new DbalMilestoneStore(Database::connection());
    }
}
