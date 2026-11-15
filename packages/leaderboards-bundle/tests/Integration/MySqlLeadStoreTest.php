<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store\LeadStoreTestCase;

/**
 * The contract of a lead store on a real MySQL server.
 */
#[CoversClass(DbalLeadStore::class)]
#[CoversClass(LeaderboardsTables::class)]
#[CoversClass(Rows::class)]
#[Group('mysql')]
final class MySqlLeadStoreTest extends LeadStoreTestCase
{
    use OnMySql;
    use ServerStores;

    #[Override]
    protected function store(): LeadStore
    {
        return new DbalLeadStore($this->server());
    }
}
