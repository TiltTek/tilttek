<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Booking;

/**
 * How the seats of a session are sold.
 */
enum SessionMode: string
{
    /** One party books the whole room. */
    case Private = 'private';

    /** Parties book seats and play together, up to the room's largest team. */
    case Shared = 'shared';

    /** The first booking decides: a private booking takes the whole room, a booking of seats shares it. */
    case Either = 'either';
}
