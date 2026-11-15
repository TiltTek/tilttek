<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Lead;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Database;

#[CoversClass(DbalLeadStore::class)]
#[CoversClass(Lead::class)]
#[CoversClass(Rows::class)]
final class DbalLeadStoreTest extends LeadStoreTestCase
{
    protected function store(): LeadStore
    {
        return new DbalLeadStore(Database::connection());
    }
}
