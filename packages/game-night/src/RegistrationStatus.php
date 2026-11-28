<?php

declare(strict_types=1);

namespace Tilttek\GameNight;

/**
 * Whether a sign-up holds its places or waits for them.
 */
enum RegistrationStatus: string
{
    /** The member, and their guest, hold their places. */
    case Confirmed = 'confirmed';

    /** The member waits on the waiting list, in the order they signed up. */
    case Waitlisted = 'waitlisted';
}
