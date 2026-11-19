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
 * The contract of a store of offers on a real MySQL server.
 */
#[CoversClass(DbalOfferStore::class)]
#[CoversClass(ExchangeTables::class)]
#[CoversClass(Rows::class)]
#[Group('mysql')]
final class MySqlOfferStoreTest extends OfferStoreTestCase
{
    use OnMySql;
    use ServerStores;

    #[Override]
    protected function store(): OfferStore
    {
        return new DbalOfferStore($this->server());
    }
}
