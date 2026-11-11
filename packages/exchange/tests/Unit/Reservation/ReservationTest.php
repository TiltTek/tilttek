<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Reservation;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Reservation\ReservationStatus;

#[CoversClass(Reservation::class)]
#[CoversClass(ReservationStatus::class)]
final class ReservationTest extends TestCase
{
    public function testAReservationAndItsSettlement(): void
    {
        $reservation = $this->reservation();

        self::assertSame(ReservationStatus::Reserved, $reservation->status);
        self::assertNull($reservation->settledAt);
        self::assertTrue($reservation->asksFor(ResourceReference::of('member', '1'), 'seat', 2));
        self::assertFalse($reservation->asksFor(ResourceReference::of('member', '2'), 'seat', 2));
        self::assertFalse($reservation->asksFor(ResourceReference::of('member', '1'), 'other', 2));
        self::assertFalse($reservation->asksFor(ResourceReference::of('member', '1'), 'seat', 1));
        self::assertSame('reward:seat', $reservation->redemption());
        self::assertSame('exchange:reservation:order:1', $reservation->spendingKey());

        self::assertFalse($reservation->isDue(new DateTimeImmutable('2026-11-07 12:29:59 UTC')));
        self::assertTrue($reservation->isDue(new DateTimeImmutable('2026-11-07 12:30:00 UTC')));

        $confirmed = $reservation->settled(ReservationStatus::Confirmed, new DateTimeImmutable('2026-11-07 12:31:00 UTC'));
        self::assertFalse($confirmed->isDue(new DateTimeImmutable('2026-11-08 00:00:00 UTC')));
        self::assertSame([
            'key' => 'order:1',
            'member' => 'member:1',
            'reward' => 'seat',
            'quantity' => 2,
            'amount' => 500,
            'currency' => 'points',
            'status' => 'confirmed',
            'reserved_at' => '2026-11-07T12:00:00+00:00',
            'expires_at' => '2026-11-07T12:30:00+00:00',
            'settled_at' => '2026-11-07T12:31:00+00:00',
        ], $confirmed->toArray());
        self::assertNull($reservation->toArray()['settled_at']);
        self::assertSame([ReservationStatus::Reserved, ReservationStatus::Confirmed], ReservationStatus::holding());
    }

    public function testAReservationHoldsAUnit(): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('A reservation holds at least one unit for at least one point; "order:1" holds 0 for 500.');

        $this->reservation(quantity: 0);
    }

    public function testAReservationCostsAPoint(): void
    {
        self::assertSame(1, $this->reservation(amount: 1)->amount);
        self::assertSame(1, $this->reservation(quantity: 1)->quantity);

        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('"order:1" holds 2 for 0.');

        $this->reservation(amount: 0);
    }

    public function testAReservationExpiresAfterItIsMade(): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('The reservation "order:1" expires before it is made.');

        $this->reservation(expiresAt: '2026-11-07 12:00:00 UTC');
    }

    public function testAReservedReservationIsNotSettled(): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('The reservation "order:1" is settled when, and only when, it is no longer reserved.');

        $this->reservation(settledAt: new DateTimeImmutable());
    }

    public function testASettledReservationHasItsMoment(): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('is settled when, and only when');

        $this->reservation(status: ReservationStatus::Cancelled);
    }

    private function reservation(int $quantity = 2, int $amount = 500, string $expiresAt = '2026-11-07 12:30:00 UTC', ReservationStatus $status = ReservationStatus::Reserved, ?DateTimeImmutable $settledAt = null): Reservation
    {
        return new Reservation(ExchangeKey::fromString('order:1'), ResourceReference::of('member', '1'), 'seat', $quantity, $amount, 'points', new DateTimeImmutable('2026-11-07 12:00:00 UTC'), new DateTimeImmutable($expiresAt), $status, $settledAt);
    }
}
