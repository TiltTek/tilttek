<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Tests\Unit\Booking;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Contract\SessionReservations;
use Tilttek\EscapeRoom\Store\InMemorySessionReservations;

#[CoversClass(InMemorySessionReservations::class)]
final class InMemorySessionReservationsTest extends SessionReservationsTestCase
{
    public function testAResetForgetsEveryBooking(): void
    {
        $reservations = new InMemorySessionReservations();
        $session = self::session('s-1');
        $reservations->reserve($session, new SeatBooking('a', 's-1', ResourceReference::of('member', 'ana'), 1, false), 6);

        $reservations->reset();

        self::assertSame([], $reservations->bookings($session));
    }

    #[Override]
    protected function reservations(): SessionReservations
    {
        return new InMemorySessionReservations();
    }
}
