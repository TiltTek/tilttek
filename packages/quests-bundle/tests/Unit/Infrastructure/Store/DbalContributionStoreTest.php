<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Quests\Store\ContributionStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalContributionStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\QuestsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalContributionStore::class)]
#[CoversClass(Rows::class)]
final class DbalContributionStoreTest extends ContributionStoreTestCase
{
    protected function store(): ContributionStore
    {
        return new DbalContributionStore(Database::connection());
    }
}
