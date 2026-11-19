<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Booking;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\EscapeRoom\Exception\InvalidRoom;

/**
 * A session of a room: when it starts and ends, and how its seats are sold.
 */
final readonly class Session
{
    public DateTimeImmutable $startsAt;

    public DateTimeImmutable $endsAt;

    /**
     * @param string $id   the session's identifier in the application's reservations
     * @param string $room the identifier of the room
     *
     * @throws InvalidRoom when an identifier is empty, or the session does not end after it starts
     */
    public function __construct(
        public string $id,
        public string $room,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt,
        public SessionMode $mode = SessionMode::Either,
    ) {
        if ('' === $id || '' === $room) {
            throw InvalidRoom::because('A session names itself and its room.');
        }

        if ($endsAt->getTimestamp() <= $startsAt->getTimestamp()) {
            throw InvalidRoom::because(\sprintf('The session "%s" ends after it starts.', $id));
        }

        $this->startsAt = $startsAt->setTimezone(new DateTimeZone('UTC'));
        $this->endsAt = $endsAt->setTimezone(new DateTimeZone('UTC'));
    }
}
