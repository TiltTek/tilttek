<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reservation;

/**
 * Where a reservation stands: `Paying` while its points are being spent, then `Reserved` until the application
 * confirms the delivery — `Confirmed` — or cancels it — `Cancelled` —, or until it outlives its lifetime — `Expired`.
 * The units of a paying, reserved or confirmed reservation use up the reward's stock; the points of a cancelled or
 * expired one come back to the member.
 */
enum ReservationStatus: string
{
    case Paying = 'paying';

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
        return [self::Paying, self::Reserved, self::Confirmed];
    }

    /**
     * Whether a reservation in this status is not settled yet: paying or reserved.
     */
    public function isOpen(): bool
    {
        return self::Paying === $this || self::Reserved === $this;
    }
}
