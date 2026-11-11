<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Reservation;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Reservation\ReservationStatus;
use Tilttek\Exchange\Reservation\ReservationStore;

/**
 * The contract every store of reservations passes: a store of the application's storage extends this case and builds
 * itself in `store()`.
 */
abstract class ReservationStoreTestCase extends TestCase
{
    abstract protected function store(): ReservationStore;

    public function testAReservationIsAddedOnceByItsKey(): void
    {
        $store = $this->store();

        self::assertTrue($store->add(self::reservation('a', 1, 'seat', 2), null, null));
        self::assertFalse($store->add(self::reservation('a', 2, 'other', 1), null, null));
        self::assertEquals(self::reservation('a', 1, 'seat', 2), $store->find(ExchangeKey::fromString('a')));
        self::assertNull($store->find(ExchangeKey::fromString('b')));
        self::assertSame(2, $store->held('seat'));
        self::assertSame(0, $store->held('other'));
    }

    public function testTheStockAndTheMembersLimitAreCheckedAsTheReservationIsWritten(): void
    {
        $store = $this->store();

        self::assertTrue($store->add(self::reservation('a', 1, 'seat', 2), 3, 2));
        $limit = $this->refusal(static fn (): bool => $store->add(self::reservation('b', 1, 'seat', 1), 3, 2));
        self::assertSame(Refusal::MemberLimitReached, $limit->refusal);
        self::assertSame('A member holds at most 2 units of "seat".', $limit->getMessage());

        self::assertTrue($store->add(self::reservation('c', 2, 'seat', 1), 3, 2));
        $stock = $this->refusal(static fn (): bool => $store->add(self::reservation('d', 3, 'seat', 1), 3, null));
        self::assertSame(Refusal::OutOfStock, $stock->refusal);
        self::assertSame('The reward "seat" has not 1 units left.', $stock->getMessage());
        self::assertNull($store->find(ExchangeKey::fromString('d')));
        self::assertTrue($store->add(self::reservation('e', 3, 'other', 5), 5, 5));
        self::assertSame(3, $store->held('seat'));
        self::assertSame(2, $store->held('seat', self::member(1)));
        self::assertSame(1, $store->held('seat', self::member(2)));
        self::assertSame(0, $store->held('seat', self::member(3)));

        // A cancelled or expired reservation gives its units back; a confirmed one keeps them.
        self::assertTrue($store->settle(ExchangeKey::fromString('a'), ReservationStatus::Cancelled, self::at('12:10')));
        self::assertTrue($store->settle(ExchangeKey::fromString('c'), ReservationStatus::Confirmed, self::at('12:10')));
        self::assertSame(1, $store->held('seat'));
        self::assertTrue($store->add(self::reservation('f', 1, 'seat', 2), 3, 2));
        self::assertSame(3, $store->held('seat'));
        self::assertTrue($store->settle(ExchangeKey::fromString('f'), ReservationStatus::Expired, self::at('12:40')));
        self::assertSame(1, $store->held('seat'));
    }

    public function testAReservationLeavesReservedOnce(): void
    {
        $store = $this->store();
        $store->add(self::reservation('a', 1, 'seat', 1), null, null);

        self::assertFalse($store->settle(ExchangeKey::fromString('b'), ReservationStatus::Confirmed, self::at('12:10')));
        self::assertTrue($store->settle(ExchangeKey::fromString('a'), ReservationStatus::Confirmed, self::at('12:10')));
        self::assertFalse($store->settle(ExchangeKey::fromString('a'), ReservationStatus::Cancelled, self::at('12:11')));

        $settled = $store->find(ExchangeKey::fromString('a'));
        self::assertSame(ReservationStatus::Confirmed, $settled?->status);
        self::assertSame('2026-11-07 12:10:00', $settled->settledAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    }

    public function testOnlyAReservedReservationIsDiscarded(): void
    {
        $store = $this->store();
        $store->add(self::reservation('a', 1, 'seat', 1), null, null);
        $store->add(self::reservation('b', 1, 'seat', 1), null, null);
        $store->settle(ExchangeKey::fromString('b'), ReservationStatus::Confirmed, self::at('12:10'));

        $store->discard(ExchangeKey::fromString('a'));
        $store->discard(ExchangeKey::fromString('b'));
        $store->discard(ExchangeKey::fromString('c'));

        self::assertNull($store->find(ExchangeKey::fromString('a')));
        self::assertSame(ReservationStatus::Confirmed, $store->find(ExchangeKey::fromString('b'))?->status);
        self::assertTrue($store->add(self::reservation('a', 1, 'seat', 1), null, null));
    }

    public function testTheDueReservationsComeOldestFirst(): void
    {
        $store = $this->store();
        $store->add(self::reservation('c', 1, 'seat', 1, '12:20'), null, null);
        $store->add(self::reservation('b', 2, 'seat', 1, '12:10'), null, null);
        $store->add(self::reservation('a', 3, 'seat', 1, '12:20'), null, null);
        $store->add(self::reservation('d', 1, 'seat', 1, '12:30'), null, null);
        $store->add(self::reservation('e', 1, 'seat', 1, '12:05'), null, null);
        $store->settle(ExchangeKey::fromString('e'), ReservationStatus::Confirmed, self::at('12:01'));

        self::assertSame(['b', 'a', 'c'], $this->keys($store->due(self::at('12:20'), 10)));
        self::assertSame(['b', 'a'], $this->keys($store->due(self::at('12:20'), 2)));
        self::assertSame([], $this->keys($store->due(self::at('12:09'), 10)));
        self::assertSame(['b', 'a', 'c', 'd'], $this->keys($store->due(self::at('13:00'), 10)));
    }

    public function testAMembersReservationsAndTheirErasure(): void
    {
        $store = $this->store();
        $store->add(self::reservation('a', 1, 'seat', 1, '12:30', '12:00'), null, null);
        $store->add(self::reservation('b', 2, 'seat', 1, '12:30', '12:01'), null, null);
        $store->add(self::reservation('c', 1, 'other', 1, '12:30', '12:02'), null, null);

        self::assertSame(['a', 'c'], $this->keys($store->ofMember(self::member(1))));
        self::assertSame([], $store->ofMember(self::member(3)));
        self::assertSame(2, $store->eraseMember(self::member(1)));
        self::assertSame(0, $store->eraseMember(self::member(1)));
        self::assertSame([], $store->ofMember(self::member(1)));
        self::assertSame(['b'], $this->keys($store->ofMember(self::member(2))));
        self::assertSame(1, $store->held('seat'));
    }

    protected static function reservation(string $key, int $member, string $reward, int $quantity, string $expiresAt = '12:30', string $reservedAt = '12:00'): Reservation
    {
        return new Reservation(ExchangeKey::fromString($key), self::member($member), $reward, $quantity, 10 * $quantity, 'points', self::at($reservedAt), self::at($expiresAt));
    }

    protected static function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    protected static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-07 '.$time.':00 UTC');
    }

    /**
     * @param list<Reservation> $reservations
     *
     * @return list<string>
     */
    private function keys(array $reservations): array
    {
        return array_map(static fn (Reservation $reservation): string => $reservation->key->value, $reservations);
    }

    private function refusal(callable $add): ExchangeRefused
    {
        try {
            $add();
        } catch (ExchangeRefused $exchangeRefused) {
            return $exchangeRefused;
        }

        self::fail('A refusal was expected.');
    }
}
