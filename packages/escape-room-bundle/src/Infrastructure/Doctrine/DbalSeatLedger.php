<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Doctrine;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\EscapeRoom\Booking\BookingRefusal;
use Tilttek\EscapeRoom\Booking\SeatBooking;
use Tilttek\EscapeRoom\Booking\Session;
use Tilttek\EscapeRoom\Contract\SeatLedger;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoomBundle\Application\Contract\PartyBookings;

/**
 * The bookings of the sessions in `tilttek_escape_room_booking`. A booking is decided and written in one transaction
 * that first writes the session's row of `tilttek_escape_room_session_lock`: every database locks the row it updates
 * until the transaction ends, so a second booking of the same session waits, then reads the first one.
 */
final readonly class DbalSeatLedger implements SeatLedger, PartyBookings
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function bookings(Session $session): array
    {
        return $this->read($this->connection, $session->id);
    }

    /**
     * @throws InvalidRoom when an identifier is longer than 255 bytes
     */
    #[Override]
    public function hold(Session $session, SeatBooking $booking, Closure $decide): ?BookingRefusal
    {
        if (\strlen($session->id) > EscapeRoomTables::IDENTIFIER_LENGTH || \strlen($booking->id) > EscapeRoomTables::IDENTIFIER_LENGTH) {
            throw InvalidRoom::because(\sprintf('The identifier of a session or a booking holds %d bytes at most.', EscapeRoomTables::IDENTIFIER_LENGTH));
        }

        $now = $this->clock->now();

        return $this->connection->transactional(function (Connection $connection) use ($session, $booking, $decide, $now): ?BookingRefusal {
            $connection->executeStatement(
                Rows::ignoring($connection->getDatabasePlatform(), 'INTO '.EscapeRoomTables::SESSION_LOCKS.' (session, locked_at) VALUES (?, ?)', 'session'),
                [$session->id, $now],
                [1 => Types::DATETIME_UTC_IMMUTABLE],
            );
            $connection->update(EscapeRoomTables::SESSION_LOCKS, ['locked_at' => $now], ['session' => $session->id], ['locked_at' => Types::DATETIME_UTC_IMMUTABLE]);

            $held = $this->read($connection, $session->id);
            $refusal = $decide($held);

            if (!$refusal instanceof BookingRefusal) {
                $connection->insert(EscapeRoomTables::BOOKINGS, [
                    'id' => $booking->id,
                    'session' => $session->id,
                    'room' => $session->room,
                    'party' => $booking->party->toString(),
                    'seats' => $booking->seats,
                    'private' => $booking->private,
                    'position' => \count($held) + 1,
                    'booked_at' => $now,
                ], ['private' => Types::BOOLEAN, 'booked_at' => Types::DATETIME_UTC_IMMUTABLE]);
            }

            return $refusal;
        });
    }

    #[Override]
    public function ofParty(ResourceReference $party): array
    {
        return array_map($this->booking(...), $this->connection->fetchAllAssociative('SELECT * FROM '.EscapeRoomTables::BOOKINGS.' WHERE party = ? ORDER BY session, position', [$party->toString()]));
    }

    #[Override]
    public function eraseParty(ResourceReference $party, ResourceReference $pseudonym): int
    {
        return (int) $this->connection->update(EscapeRoomTables::BOOKINGS, ['party' => $pseudonym->toString()], ['party' => $party->toString()]);
    }

    /**
     * @return list<SeatBooking>
     */
    private function read(Connection $connection, string $session): array
    {
        return array_map($this->booking(...), $connection->fetchAllAssociative('SELECT * FROM '.EscapeRoomTables::BOOKINGS.' WHERE session = ? ORDER BY position', [$session]));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function booking(array $row): SeatBooking
    {
        return new SeatBooking(
            Rows::text($row['id']),
            Rows::text($row['session']),
            ResourceReference::fromString(Rows::text($row['party'])),
            Rows::integer($row['seats']),
            Rows::flag($row['private']),
        );
    }
}
