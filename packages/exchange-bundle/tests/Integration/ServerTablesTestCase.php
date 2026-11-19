<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Reservation\ReservationStatus;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\Exchange\Transfer\Transfer;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalCatalog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalReservationStore;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\DbalTransferLog;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\Rows;

/**
 * What the contracts do not show of a real server: the tables and the index of the due reservations as the server
 * reads them back, moments of another zone kept in UTC, the catalogue kept and read back, and a row already there
 * ignored without aborting the transaction around it.
 */
abstract class ServerTablesTestCase extends TestCase
{
    public function testTheServerHasTheTablesAndTheIndexOfTheDueReservations(): void
    {
        $manager = $this->server()->createSchemaManager();
        $index = array_find($manager->introspectTableIndexesByUnquotedName(ExchangeTables::RESERVATIONS), static fn (Index $index): bool => 'tilttek_exchange_reservation_due' === strtolower($index->getObjectName()->getIdentifier()->getValue()));

        self::assertNotNull($index);
        self::assertSame(['status', 'expires_at'], array_map(static fn (IndexedColumn $column): string => strtolower($column->getColumnName()->getIdentifier()->getValue()), $index->getIndexedColumns()));

        foreach ([ExchangeTables::REWARDS, ExchangeTables::STOCK_LOCKS, ExchangeTables::TRANSFERS, ExchangeTables::OFFERS, ExchangeTables::OFFER_ITEMS, ExchangeTables::HOLDS] as $table) {
            self::assertTrue($manager->tableExists($table), $table);
        }
    }

    public function testAMomentOfAnotherZoneIsKeptInUtc(): void
    {
        $store = new DbalReservationStore($this->server());
        $toronto = new DateTimeZone('America/Toronto');
        $store->add(new Reservation(ExchangeKey::fromString('a'), ResourceReference::fromString('member:1'), 'seat', 1, 100, 'points', new DateTimeImmutable('2026-11-07 07:00:00', $toronto), new DateTimeImmutable('2026-11-07 07:30:00', $toronto)), 5, null);

        self::assertStringStartsWith('2026-11-07 12:30:00', Rows::text($this->server()->fetchOne('SELECT expires_at FROM '.ExchangeTables::RESERVATIONS)));
        self::assertSame('2026-11-07T12:00:00+00:00', $store->find(ExchangeKey::fromString('a'))?->reservedAt->format(\DATE_ATOM));
        self::assertSame([], $store->due(new DateTimeImmutable('2026-11-07 07:29:59', $toronto), 10));
        self::assertCount(1, $store->due(new DateTimeImmutable('2026-11-07 07:30:00', $toronto), 10));

        self::assertTrue($store->transition(ExchangeKey::fromString('a'), ReservationStatus::Reserved, ReservationStatus::Cancelled, new DateTimeImmutable('2026-11-07 07:10:00', $toronto)));
        $settled = $store->find(ExchangeKey::fromString('a'));
        self::assertInstanceOf(Reservation::class, $settled);
        self::assertSame('2026-11-07T12:10:00+00:00', $settled->settledAt?->format(\DATE_ATOM));
    }

    public function testTheCatalogueIsKeptAndReadBack(): void
    {
        $catalog = new DbalCatalog($this->server());
        $catalog->save(new Reward('seat', 250, 'stars', 30, new DateTimeImmutable('2026-11-01 00:00:00 America/Toronto'), null, 2, ['badge:helper']));
        $catalog->save(new Reward('seat', 300, null, 30, new DateTimeImmutable('2026-11-01 00:00:00 America/Toronto'), null, 2, ['badge:helper']));

        $seat = $catalog->get('seat');
        self::assertSame([300, '2026-11-01T04:00:00+00:00', null, ['badge:helper']], [$seat->price, $seat->availableFrom?->format(\DATE_ATOM), $seat->availableUntil, $seat->requires]);
        self::assertCount(1, $catalog->all());
    }

    public function testARowAlreadyThereIsIgnoredWithoutAbortingTheTransaction(): void
    {
        $log = new DbalTransferLog($this->server());
        $at = new DateTimeImmutable('2026-11-07 12:00:00 UTC');
        $transfer = new Transfer(ExchangeKey::fromString('gift:1'), ResourceReference::fromString('member:1'), ResourceReference::fromString('member:2'), 10, 'points', $at);

        $this->server()->transactional(static function () use ($log, $transfer, $at): void {
            self::assertTrue($log->record($transfer));
            self::assertFalse($log->record($transfer));
            self::assertTrue($log->record(new Transfer(ExchangeKey::fromString('gift:2'), ResourceReference::fromString('member:1'), ResourceReference::fromString('member:3'), 5, 'points', $at)));
        });

        self::assertSame(2, Rows::integer($this->server()->fetchOne('SELECT COUNT(*) FROM '.ExchangeTables::TRANSFERS)));
    }

    abstract protected function server(): Connection;
}
