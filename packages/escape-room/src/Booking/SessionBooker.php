<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Booking;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\NativeClock;
use Tilttek\EscapeRoom\Contract\SessionReservations;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\EscapeRoom\Event\SeatsBooked;
use Tilttek\EscapeRoom\Exception\BookingRefused;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\Room;

/**
 * Books the sessions of a room: a private session for one party, which takes the whole room, or seats of a shared
 * session, up to the room's largest team. The rules are checked against the bookings already made, then the
 * reservations hold the booking and check again.
 *
 * The service keeps nothing between two calls.
 */
final readonly class SessionBooker
{
    public function __construct(
        private SessionReservations $reservations,
        private ?EventDispatcherInterface $events = null,
        private ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * The seats a party may still book in the session: 0 once a private booking holds it.
     */
    public function free(Room $room, Session $session): int
    {
        $free = $room->maxPlayers;

        foreach ($this->reservations->bookings($session) as $booking) {
            if ($booking->private) {
                return 0;
            }

            $free -= $booking->seats;
        }

        return max(0, $free);
    }

    /**
     * @param string $booking the booking's identifier, chosen by the application
     *
     * @throws BookingRefused when the room's rules or the reservations refuse it, with the reason
     * @throws InvalidRoom    when the identifier is empty, or the party is named by an email address or erased
     */
    public function book(Room $room, Session $session, ResourceReference $party, int $seats, bool $private, string $booking): SeatBooking
    {
        if ('' === $booking) {
            throw InvalidRoom::because('A booking names itself.');
        }

        if (str_contains($party->id, '@')) {
            throw InvalidRoom::because('A party is named by a stable identifier, never an email address.');
        }

        if (EscapeRooms::ERASED_KIND === $party->key) {
            throw InvalidRoom::erased('party');
        }

        $refusal = $this->refusal($room, $session, $party, $seats, $private);

        if ($refusal instanceof BookingRefusal) {
            throw BookingRefused::because($refusal, $session->id);
        }

        $made = new SeatBooking($booking, $session->id, $party, $seats, $private);

        $refused = $this->reservations->reserve($session, $made, $room->maxPlayers);

        if ($refused instanceof BookingRefusal) {
            throw BookingRefused::because($refused, $session->id);
        }

        $this->events?->dispatch(new SeatsBooked($room->id, $session->id, $booking, $party, $seats, $private));

        return $made;
    }

    private function refusal(Room $room, Session $session, ResourceReference $party, int $seats, bool $private): ?BookingRefusal
    {
        $bookings = $this->reservations->bookings($session);

        return match (true) {
            $session->room !== $room->id => BookingRefusal::OtherRoom,
            $session->startsAt->getTimestamp() <= $this->clock->now()->getTimestamp() => BookingRefusal::Started,
            $private && SessionMode::Shared === $session->mode => BookingRefusal::SharedOnly,
            !$private && SessionMode::Private === $session->mode => BookingRefusal::PrivateOnly,
            $seats < ($private ? $room->minPlayers : 1) => BookingRefusal::TooFewPlayers,
            $seats > $room->maxPlayers => BookingRefusal::TooManyPlayers,
            [] !== array_filter($bookings, static fn (SeatBooking $held): bool => $held->party->equals($party)) => BookingRefusal::AlreadyBooked,
            ($private && [] !== $bookings) || $seats > $this->free($room, $session) => BookingRefusal::Full,
            default => null,
        };
    }
}
