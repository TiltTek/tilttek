<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Trade\InMemoryInventory;
use Tilttek\Exchange\Trade\Inventory;
use Tilttek\Exchange\Trade\OfferStatus;
use Tilttek\Exchange\Trade\Trades;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalOfferStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalTransaction;
use Tilttek\ExchangeBundle\Tests\Unit\Fixture\Clock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Points;
use Tilttek\PointsBundle\Infrastructure\Doctrine\DbalLedger;

#[CoversClass(DbalTransaction::class)]
final class DbalTransactionTest extends TestCase
{
    public function testTheStepsCommitTogether(): void
    {
        $connection = Database::connection();
        $transaction = new DbalTransaction($connection);

        self::assertSame(42, $transaction->run(static function () use ($connection): int {
            self::assertTrue($connection->isTransactionActive());

            return 42;
        }));
        self::assertFalse($connection->isTransactionActive());
    }

    public function testAFailingStepRollsTheOthersBack(): void
    {
        $connection = Database::connection();
        $clock = new Clock();
        $currencies = new Currencies(new Currency('points'));
        $points = new Points(new DbalLedger($connection), $clock, $currencies);
        $offers = new DbalOfferStore($connection);
        $items = new InMemoryInventory();
        $items->give(ResourceReference::fromString('card:1'), $this->member(1));

        $failing = new readonly class($items) implements Inventory {
            public function __construct(
                private Inventory $inner,
            ) {
            }

            public function ownerOf(ResourceReference $item): ?ResourceReference
            {
                return $this->inner->ownerOf($item);
            }

            public function isTradable(ResourceReference $item): bool
            {
                return $this->inner->isTradable($item);
            }

            public function move(array $moves): void
            {
                throw new RuntimeException('The inventory is down.');
            }
        };
        $points->grant($this->member(1), 100, 'welcome:1');
        $trades = new Trades($offers, $failing, $points, $currencies, $clock, transaction: new DbalTransaction($connection));
        $trades->offer('t1', $this->member(1), $this->member(2), [ResourceReference::fromString('card:1')], [], 40);

        try {
            $trades->accept('t1', $this->member(2));
            self::fail('The inventory was expected to fail.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The inventory is down.', $runtimeException->getMessage());
        }

        self::assertSame(OfferStatus::Open, $trades->get('t1')->status);
        self::assertSame('t1', $offers->holder(ResourceReference::fromString('card:1'))?->value);
        self::assertSame(100, $points->balance($this->member(1)));
        self::assertSame(0, $points->balance($this->member(2)));

        $working = new Trades($offers, $items, $points, $currencies, $clock, transaction: new DbalTransaction($connection));
        self::assertSame(OfferStatus::Accepted, $working->accept('t1', $this->member(2))->status);
        self::assertSame(60, $points->balance($this->member(1)));
        self::assertSame(40, $points->balance($this->member(2)));
        self::assertTrue($items->ownerOf(ResourceReference::fromString('card:1'))?->equals($this->member(2)));

        try {
            $trades->offer('t2', $this->member(2), $this->member(1), [ResourceReference::fromString('card:9')], []);
            self::fail('A refusal was expected.');
        } catch (ExchangeRefused) {
            self::assertNull($offers->find(ExchangeKey::fromString('t2')));
        }
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }
}
