<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Exchange\Reservation\ReservationStore;
use Tilttek\Exchange\Trade\OfferStatus;
use Tilttek\Exchange\Trade\OfferStore;
use Tilttek\Exchange\Transfer\TransferLog;

/**
 * Erases a member's exchanges: their reservations are deleted; their open offers are withdrawn, freeing the items, and
 * `erased:member` takes their place in the offers and the transfers they had with other members, which stay those
 * members' trades and points. The points themselves are erased by `tilttek/points`.
 */
final readonly class ExchangeErasure
{
    public function __construct(
        private ReservationStore $reservations,
        private ClockInterface $clock,
        private ?TransferLog $transfers = null,
        private ?OfferStore $offers = null,
    ) {
    }

    public function erase(ResourceReference $member): Erasure
    {
        $offers = 0;

        if ($this->offers instanceof OfferStore) {
            foreach ($this->offers->ofMember($member) as $offer) {
                $this->offers->close($offer->key, OfferStatus::Withdrawn, $this->clock->now());
            }

            $offers = $this->offers->eraseMember($member);
        }

        return new Erasure(
            $this->reservations->eraseMember($member),
            $this->transfers?->eraseMember($member) ?? 0,
            $offers,
        );
    }
}
