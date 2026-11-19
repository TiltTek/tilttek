<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Reservation\ReservationStatus;
use Tilttek\Exchange\Reservation\ReservationStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalReservationStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\Rows;

#[CoversClass(DbalReservationStore::class)]
#[CoversClass(Rows::class)]
final class DbalReservationStoreTest extends ReservationStoreTestCase
{
    #[Override]
    protected function store(): ReservationStore
    {
        return new DbalReservationStore(Database::connection());
    }

    public function testAReservationWithAStockOrALimitTakesTheRewardsLock(): void
    {
        $connection = Database::connection();
        $store = new DbalReservationStore($connection);

        $store->add(self::reservation('a', 1, 'free', 1), null, null);
        self::assertSame([], $connection->fetchFirstColumn('SELECT reward FROM '.ExchangeTables::STOCK_LOCKS));

        $store->add(self::reservation('b', 1, 'seat', 1), 5, null);
        $store->add(self::reservation('c', 2, 'seat', 1), 5, null);
        $store->add(self::reservation('d', 2, 'pin', 1), null, 3);
        self::assertSame([['pin', 1], ['seat', 2]], array_map(static fn (array $row): array => [$row['reward'], Rows::integer($row['reservations'])], $connection->fetchAllAssociative('SELECT reward, reservations FROM '.ExchangeTables::STOCK_LOCKS.' ORDER BY reward')));

        // A reservation already written takes no lock.
        self::assertFalse($store->add(self::reservation('b', 1, 'seat', 1), 5, null));
        self::assertSame(2, Rows::integer($connection->fetchOne('SELECT reservations FROM '.ExchangeTables::STOCK_LOCKS." WHERE reward = 'seat'")));
    }

    public function testARefusedReservationWritesNothing(): void
    {
        $connection = Database::connection();
        $store = new DbalReservationStore($connection);
        $store->add(self::reservation('a', 1, 'seat', 1), 1, null);

        try {
            $store->add(self::reservation('b', 2, 'seat', 1), 1, null);
            self::fail('A refusal was expected.');
        } catch (ExchangeRefused) {
            self::assertNull($store->find(ExchangeKey::fromString('b')));
            self::assertSame(1, Rows::integer($connection->fetchOne('SELECT reservations FROM '.ExchangeTables::STOCK_LOCKS." WHERE reward = 'seat'")));
        }
    }

    public function testAMomentOfAnotherZoneIsKeptInUtc(): void
    {
        $connection = Database::connection();
        $store = new DbalReservationStore($connection);
        $toronto = new DateTimeZone('America/Toronto');
        $reservation = new Reservation(ExchangeKey::fromString('a'), self::member(1), 'seat', 1, 100, 'points', new DateTimeImmutable('2026-11-07 07:00:00', $toronto), new DateTimeImmutable('2026-11-07 07:30:00', $toronto));
        $store->add($reservation, null, null);

        self::assertSame(['2026-11-07 12:00:00', '2026-11-07 12:30:00'], $connection->fetchNumeric('SELECT reserved_at, expires_at FROM '.ExchangeTables::RESERVATIONS));
        self::assertSame('2026-11-07T12:30:00+00:00', $store->find(ExchangeKey::fromString('a'))?->expiresAt->format(\DATE_ATOM));
        self::assertSame([], $store->due(new DateTimeImmutable('2026-11-07 07:29:59', $toronto), 10));
        self::assertCount(1, $store->due(new DateTimeImmutable('2026-11-07 07:30:00', $toronto), 10));

        self::assertTrue($store->transition(ExchangeKey::fromString('a'), ReservationStatus::Reserved, ReservationStatus::Confirmed, new DateTimeImmutable('2026-11-07 07:10:00', $toronto)));
        self::assertSame('2026-11-07 12:10:00', $connection->fetchOne('SELECT settled_at FROM '.ExchangeTables::RESERVATIONS));
    }
}
