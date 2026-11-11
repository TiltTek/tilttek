<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reservation;

/**
 * Where a reservation stands: `Reserved` until the application confirms the delivery — `Confirmed` — or cancels it —
 * `Cancelled` —, or until it outlives its lifetime — `Expired`. The units of a reserved or confirmed reservation use
 * up the reward's stock; the points of a cancelled or expired one come back to the member.
 */
enum ReservationStatus: string
{
    case Reserved = 'reserved';

    case Confirmed = 'confirmed';

    case Cancelled = 'cancelled';

    case Expired = 'expired';

    /**
     * The statuses whose units use up the stock and the member's limit.
     *
     * @return non-empty-list<self>
     */
    public static function holding(): array
    {
        return [self::Reserved, self::Confirmed];
    }
}
