<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Tests\Unit\Booking;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Booking\SessionBooker;
use Tilttek\EscapeRoom\Booking\SessionMode;
use Tilttek\EscapeRoom\Contract\SessionReservations;
use Tilttek\EscapeRoom\Event\SeatsBooked;
use Tilttek\EscapeRoom\Exception\BookingRefused;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\Room;
use Tilttek\EscapeRoom\Store\InMemorySessionReservations;
use Tilttek\EscapeRoom\Tests\Unit\Fixture\Crypt;
use Tilttek\EscapeRoom\Tests\Unit\Fixture\Recorder;

#[CoversClass(SessionBooker::class)]
#[CoversClass(Session::class)]
#[CoversClass(SeatBooking::class)]
#[CoversClass(BookingRefusal::class)]
#[CoversClass(BookingRefused::class)]
#[CoversClass(SeatsBooked::class)]
#[CoversClass(InvalidRoom::class)]
final class SessionBookerTest extends TestCase
{
    private Room $room;

    private MockClock $clock;

    private InMemorySessionReservations $reservations;

    private Recorder $events;

    private SessionBooker $booker;

    protected function setUp(): void
    {
        $this->room = Crypt::room();
        $this->clock = new MockClock('2026-11-13 20:00:00 UTC');
        $this->reservations = new InMemorySessionReservations();
        $this->events = new Recorder();
        $this->booker = new SessionBooker($this->reservations, $this->events, $this->clock);
    }

    public function testASessionKeepsItsTimesInUtc(): void
    {
        $session = new Session('s-1', 'crypt', new DateTimeImmutable('2026-11-14 19:00', new DateTimeZone('America/Toronto')), new DateTimeImmutable('2026-11-14 20:00', new DateTimeZone('America/Toronto')));

        self::assertSame('2026-11-15 00:00', $session->startsAt->format('Y-m-d H:i'));
        self::assertSame('2026-11-15 01:00', $session->endsAt->format('Y-m-d H:i'));
        self::assertSame('UTC', $session->endsAt->getTimezone()->getName());
        self::assertSame(SessionMode::Either, $session->mode);
        self::assertSame('s-1', $session->id);
        self::assertSame('crypt', $session->room);
    }

    public function testASessionWithoutIdentifierIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A session names itself and its room.');

        new Session('', 'crypt', new DateTimeImmutable('2026-11-14 19:00'), new DateTimeImmutable('2026-11-14 20:00'));
    }

    public function testASessionWithoutRoomIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A session names itself and its room.');

        new Session('s', '', new DateTimeImmutable('2026-11-14 19:00'), new DateTimeImmutable('2026-11-14 20:00'));
    }

    public function testASessionEndsAfterItStarts(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('The session "s" ends after it starts.');

        new Session('s', 'crypt', new DateTimeImmutable('2026-11-14 19:00 UTC'), new DateTimeImmutable('2026-11-14 14:00 America/Toronto'));
    }

    public function testSharedSeatsAreBookedUpToTheLargestTeam(): void
    {
        $session = $this->session(SessionMode::Shared);
        $ana = ResourceReference::of('member', 'ana');
        $booking = $this->booker->book($this->room, $session, $ana, 4, false, 'b-1');

        self::assertSame('b-1', $booking->id);
        self::assertSame('s-1', $booking->session);
        self::assertSame($ana, $booking->party);
        self::assertSame(4, $booking->seats);
        self::assertFalse($booking->private);
        self::assertSame(2, $this->booker->free($this->room, $session));
        self::assertEquals([new SeatsBooked('crypt', 's-1', 'b-1', $ana, 4, false)], $this->events->events);

        $this->booker->book($this->room, $session, ResourceReference::of('member', 'ben'), 2, false, 'b-2');
        self::assertSame(0, $this->booker->free($this->room, $session));
        $this->refused(BookingRefusal::Full, fn (): SeatBooking => $this->booker->book($this->room, $session, ResourceReference::of('member', 'cy'), 1, false, 'b-3'));
    }

    public function testOneSeatIsBookedInASharedSession(): void
    {
        self::assertSame(1, $this->booker->book($this->room, $this->session(SessionMode::Shared), ResourceReference::of('member', 'ana'), 1, false, 'b')->seats);
    }

    public function testAPrivateBookingTakesTheWholeRoom(): void
    {
        $session = $this->session(SessionMode::Either);
        $booking = $this->booker->book($this->room, $session, ResourceReference::of('team', 'owls'), 2, true, 'b-1');

        self::assertTrue($booking->private);
        self::assertSame(0, $this->booker->free($this->room, $session));
        $this->refused(BookingRefusal::Full, fn (): SeatBooking => $this->booker->book($this->room, $session, ResourceReference::of('member', 'cy'), 1, false, 'b-2'));
    }

    public function testAPrivateBookingNeedsAnEmptySession(): void
    {
        $session = $this->session(SessionMode::Either);
        $this->booker->book($this->room, $session, ResourceReference::of('member', 'ana'), 1, false, 'b-1');

        $this->refused(BookingRefusal::Full, fn (): SeatBooking => $this->booker->book($this->room, $session, ResourceReference::of('team', 'owls'), 2, true, 'b-2'));
    }

    public function testTheRulesOfTheRoomAreChecked(): void
    {
        $ana = ResourceReference::of('member', 'ana');
        $other = new Session('s-9', 'cell', new DateTimeImmutable('2026-11-14 19:00 UTC'), new DateTimeImmutable('2026-11-14 20:00 UTC'));
        $started = new Session('s-8', 'crypt', new DateTimeImmutable('2026-11-13 20:00 UTC'), new DateTimeImmutable('2026-11-13 21:00 UTC'));

        $this->refused(BookingRefusal::OtherRoom, fn (): SeatBooking => $this->booker->book($this->room, $other, $ana, 2, true, 'b'));
        $this->refused(BookingRefusal::Started, fn (): SeatBooking => $this->booker->book($this->room, $started, $ana, 2, true, 'b'));
        $this->refused(BookingRefusal::SharedOnly, fn (): SeatBooking => $this->booker->book($this->room, $this->session(SessionMode::Shared), $ana, 2, true, 'b'));
        $this->refused(BookingRefusal::PrivateOnly, fn (): SeatBooking => $this->booker->book($this->room, $this->session(SessionMode::Private), $ana, 2, false, 'b'));
        $this->refused(BookingRefusal::TooFewPlayers, fn (): SeatBooking => $this->booker->book($this->room, $this->session(SessionMode::Private), $ana, 1, true, 'b'));
        $this->refused(BookingRefusal::TooFewPlayers, fn (): SeatBooking => $this->booker->book($this->room, $this->session(SessionMode::Shared), $ana, 0, false, 'b'));
        $this->refused(BookingRefusal::TooManyPlayers, fn (): SeatBooking => $this->booker->book($this->room, $this->session(SessionMode::Private), $ana, 7, true, 'b'));
        $this->refused(BookingRefusal::TooManyPlayers, fn (): SeatBooking => $this->booker->book($this->room, $this->session(SessionMode::Shared), $ana, 7, false, 'b'));
        self::assertSame([], $this->events->events);
    }

    public function testAPartyBooksASessionOnce(): void
    {
        $session = $this->session(SessionMode::Shared);
        $this->booker->book($this->room, $session, ResourceReference::of('member', 'ana'), 1, false, 'b-1');

        $this->refused(BookingRefusal::AlreadyBooked, fn (): SeatBooking => $this->booker->book($this->room, $session, ResourceReference::of('member', 'ana'), 1, false, 'b-2'));
    }

    public function testTheLargestPrivateTeamIsTaken(): void
    {
        self::assertSame(6, $this->booker->book($this->room, $this->session(SessionMode::Private), ResourceReference::of('team', 'owls'), 6, true, 'b')->seats);
    }

    public function testTheReservationsHaveTheLastWord(): void
    {
        $booker = new SessionBooker(new class implements SessionReservations {
            #[Override]
            public function bookings(Session $session): array
            {
                return [];
            }

            #[Override]
            public function reserve(Session $session, SeatBooking $booking, int $capacity): BookingRefusal
            {
                return BookingRefusal::Closed;
            }
        }, $this->events, $this->clock);

        $this->refused(BookingRefusal::Closed, fn (): SeatBooking => $booker->book($this->room, $this->session(SessionMode::Shared), ResourceReference::of('member', 'ana'), 1, false, 'b'));
        self::assertSame([], $this->events->events);
    }

    public function testAnErasedPartyIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A party is never an erased reference: only an erasure writes one.');

        $this->booker->book($this->room, $this->session(SessionMode::Shared), ResourceReference::of('erased', '0123456789abcdef0123456789abcdef'), 1, false, 'b');
    }

    public function testABookingWithoutIdentifierIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A booking names itself.');

        $this->booker->book($this->room, $this->session(SessionMode::Shared), ResourceReference::of('member', 'ana'), 1, false, '');
    }

    public function testAPartyNamedByAnEmailAddressIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A party is named by a stable identifier, never an email address.');

        $this->booker->book($this->room, $this->session(SessionMode::Shared), ResourceReference::of('member', 'ana@example.org'), 1, false, 'b');
    }

    public function testTheSeatsLeftNeverGoUnderZero(): void
    {
        $booker = new SessionBooker(new class implements SessionReservations {
            #[Override]
            public function bookings(Session $session): array
            {
                $ana = ResourceReference::of('member', 'ana');

                return [new SeatBooking('a', 's-1', $ana, 5, false), new SeatBooking('b', 's-1', $ana, 4, false)];
            }

            #[Override]
            public function reserve(Session $session, SeatBooking $booking, int $capacity): ?BookingRefusal
            {
                return null;
            }
        }, $this->events, $this->clock);

        self::assertSame(0, $booker->free($this->room, $this->session(SessionMode::Shared)));
    }

    public function testASessionIsBookedWithoutADispatcher(): void
    {
        $booker = new SessionBooker($this->reservations, clock: $this->clock);

        self::assertSame('b', $booker->book($this->room, $this->session(SessionMode::Shared), ResourceReference::of('member', 'ana'), 1, false, 'b')->id);
    }

    public function testEveryRefusalExplainsItself(): void
    {
        foreach (BookingRefusal::cases() as $refusal) {
            self::assertStringEndsWith('.', $refusal->explain());
            self::assertStringStartsWith(\sprintf('The session "s" cannot be booked: %s', $refusal->explain()), BookingRefused::because($refusal, 's')->getMessage());
        }

        self::assertSame('the session is booked by one party, for the whole room.', BookingRefusal::PrivateOnly->explain());
        self::assertSame('the reservations refused it: another booking reached the session first.', BookingRefusal::Taken->explain());
        self::assertSame('the room is not open for the whole session.', BookingRefusal::Closed->explain());
        self::assertSame('the session starts sooner than the room takes bookings.', BookingRefusal::TooSoon->explain());
        self::assertSame('the session starts further ahead than the room takes bookings.', BookingRefusal::TooFarAhead->explain());
        self::assertSame('the session does not fit the lengths or the starts the room takes.', BookingRefusal::OffSchedule->explain());
        self::assertSame(BookingRefusal::Full, BookingRefused::because(BookingRefusal::Full, 's')->reason);
    }

    private function session(SessionMode $mode): Session
    {
        return new Session('s-1', 'crypt', new DateTimeImmutable('2026-11-13 20:00:01 UTC'), new DateTimeImmutable('2026-11-13 21:00 UTC'), $mode);
    }

    /**
     * @param callable(): mixed $book
     */
    private function refused(BookingRefusal $reason, callable $book): void
    {
        try {
            $book();
            self::fail(\sprintf('The booking is refused as %s.', $reason->value));
        } catch (BookingRefused $bookingRefused) {
            self::assertSame($reason, $bookingRefused->reason);
        }
    }
}
