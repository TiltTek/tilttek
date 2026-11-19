<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Trade\Offer;
use Tilttek\Exchange\Trade\OfferStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalOfferStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;

#[CoversClass(DbalOfferStore::class)]
final class DbalOfferStoreTest extends OfferStoreTestCase
{
    #[Override]
    protected function store(): OfferStore
    {
        return new DbalOfferStore(Database::connection());
    }

    public function testTheItemsKeepTheirOrderOnEachSide(): void
    {
        $store = new DbalOfferStore(Database::connection());
        $store->open(self::offer('a', 1, 2, ['card:9', 'card:1', 'card:5'], ['card:8', 'card:2']));

        self::assertSame(['card:9', 'card:1', 'card:5'], $store->find(ExchangeKey::fromString('a'))?->toArray()['offered']);
        self::assertSame(['card:8', 'card:2'], $store->find(ExchangeKey::fromString('a'))->toArray()['requested']);

        $store->open(self::offer('b', 1, 2, [], ['card:7']));
        $onlyRequested = $store->find(ExchangeKey::fromString('b'));
        self::assertInstanceOf(Offer::class, $onlyRequested);
        self::assertSame([[], ['card:7']], [$onlyRequested->offered, $onlyRequested->toArray()['requested']]);
    }

    public function testARefusedOfferWritesNothing(): void
    {
        $connection = Database::connection();
        $store = new DbalOfferStore($connection);
        $store->open(self::offer('a', 1, 2, ['card:2'], []));

        try {
            $store->open(self::offer('b', 1, 3, ['card:1', 'card:2'], ['card:3']));
            self::fail('A refusal was expected.');
        } catch (ExchangeRefused) {
            self::assertNull($store->find(ExchangeKey::fromString('b')));
            self::assertSame(['card:2'], $connection->fetchFirstColumn('SELECT item FROM '.ExchangeTables::HOLDS));
            self::assertSame(['a'], $connection->fetchFirstColumn('SELECT DISTINCT offer_key FROM '.ExchangeTables::OFFER_ITEMS));
        }
    }
}
