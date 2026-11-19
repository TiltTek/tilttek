<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Integration;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tilttek\Exchange\Trade\OfferStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalOfferStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\Rows;
use Tilttek\ExchangeBundle\Tests\Unit\Infrastructure\OfferStoreTestCase;

/**
 * The contract of a store of offers on a real PostgreSQL server.
 */
#[CoversClass(DbalOfferStore::class)]
#[CoversClass(ExchangeTables::class)]
#[CoversClass(Rows::class)]
#[Group('postgresql')]
final class PostgreSqlOfferStoreTest extends OfferStoreTestCase
{
    use OnPostgreSql;
    use ServerStores;

    #[Override]
    protected function store(): OfferStore
    {
        return new DbalOfferStore($this->server());
    }
}
