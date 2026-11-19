<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Points;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;
use Tilttek\Points\Exception\UnknownMovement;
use Tilttek\Points\Points;

/**
 * The points of a reservation held by spending them at once and given back by a refund, through `Points`: the
 * spending is written under the reservation's spending key on its redemption account, exactly as `Redemptions` writes
 * it, so a hold and a reservation of the same key are one spending, and either side may give it back.
 *
 * The points leave the balance when they are held, so none stays pending: `held()` is always 0 and `available()` is
 * the balance.
 */
final readonly class SpendingPointsHold implements PointsHold
{
    public function __construct(
        private Points $points,
    ) {
    }

    #[Override]
    public function hold(Reservation $reservation): bool
    {
        return $this->points->spend($reservation->member, $reservation->amount, $reservation->spendingKey(), $reservation->redemption(), $reservation->currency)->new;
    }

    #[Override]
    public function confirm(Reservation $reservation): void
    {
        // The points were spent when they were held: they stay spent.
    }

    #[Override]
    public function release(Reservation $reservation): void
    {
        try {
            $this->points->refund($reservation->spendingKey());
        } catch (UnknownMovement) {
            // Points never held have nothing to give back.
        }
    }

    #[Override]
    public function held(ResourceReference $member, string $currency): int
    {
        return 0;
    }

    #[Override]
    public function available(ResourceReference $member, string $currency): int
    {
        return $this->points->balance($member, $currency);
    }
}
