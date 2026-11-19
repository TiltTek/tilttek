<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Booking;

/**
 * Why a booking of a session is refused.
 */
enum BookingRefusal: string
{
    case OtherRoom = 'other-room';
    case Started = 'started';
    case PrivateOnly = 'private-only';
    case SharedOnly = 'shared-only';
    case TooFewPlayers = 'too-few-players';
    case TooManyPlayers = 'too-many-players';
    case AlreadyBooked = 'already-booked';
    case Full = 'full';
    case Taken = 'taken';
    case Closed = 'closed';
    case TooSoon = 'too-soon';
    case TooFarAhead = 'too-far-ahead';
    case OffSchedule = 'off-schedule';

    public function explain(): string
    {
        return match ($this) {
            self::OtherRoom => 'the session is one of another room.',
            self::Started => 'the session has started.',
            self::PrivateOnly => 'the session is booked by one party, for the whole room.',
            self::SharedOnly => 'the session shares its seats among parties.',
            self::TooFewPlayers => 'a private session takes the fewest players the room allows, or more.',
            self::TooManyPlayers => 'the party is larger than the room allows.',
            self::AlreadyBooked => 'the party holds a booking of the session already.',
            self::Full => 'the seats left, or a private booking, leave no room for the party.',
            self::Taken => 'the reservations refused it: another booking reached the session first.',
            self::Closed => 'the room is not open for the whole session.',
            self::TooSoon => 'the session starts sooner than the room takes bookings.',
            self::TooFarAhead => 'the session starts further ahead than the room takes bookings.',
            self::OffSchedule => 'the session does not fit the lengths or the starts the room takes.',
        };
    }
}
