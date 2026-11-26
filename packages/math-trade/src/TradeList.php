<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

use Tilttek\MathTrade\Contract\Wish;
use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Exception\LimitExceeded;

/**
 * The whole trade: every participant, their offers and their wishes, checked against each other and against the
 * limits. Once built, the list is valid: each identifier is unique, each wish names an offer of another participant.
 */
final readonly class TradeList
{
    /**
     * The offers by identifier: an identifier made of digits is an integer key.
     *
     * @var array<array-key, Offer>
     */
    private array $offers;

    /**
     * The owner of each offer, by identifier of the offer.
     *
     * @var array<array-key, string>
     */
    private array $owners;

    /**
     * @param list<Participant> $participants
     *
     * @throws InvalidTradeList when two participants or two offers share an identifier, or a wish names an offer
     *                          nobody makes or one of its participant's own
     * @throws LimitExceeded    when the list is larger than the limits allow
     */
    public function __construct(
        public array $participants,
        public Limits $limits = new Limits(),
    ) {
        if (\count($participants) > $limits->participants) {
            throw LimitExceeded::because(\sprintf('The trade list holds more than %d participants.', $limits->participants));
        }

        [$this->offers, $this->owners] = $this->index($participants, $limits);
        $wished = 0;

        foreach ($this->offers as $offer) {
            if (\count($offer->wants) > $limits->wishesPerOffer) {
                throw LimitExceeded::because(\sprintf('The offer "%s" lists more than %d wishes.', $offer->id, $limits->wishesPerOffer));
            }

            foreach ($offer->wants as $wish) {
                $wished += $this->checkWish($offer, $wish, $limits);
            }

            if ($wished > $limits->wishedOffers) {
                throw LimitExceeded::because(\sprintf('The trade list wishes for more than %d offers.', $limits->wishedOffers));
            }
        }
    }

    /**
     * Every offer, in the order of the participants and of their offers.
     *
     * @return list<Offer>
     */
    public function offers(): array
    {
        return array_values($this->offers);
    }

    /**
     * The offer with this identifier.
     *
     * @throws InvalidTradeList when no participant makes it
     */
    public function offer(string $id): Offer
    {
        return $this->offers[$id] ?? throw InvalidTradeList::because(\sprintf('Nobody offers "%s".', $id));
    }

    /**
     * The identifier of the participant who makes the offer.
     *
     * @throws InvalidTradeList when no participant makes it
     */
    public function ownerOf(string $offer): string
    {
        return $this->owners[$offer] ?? throw InvalidTradeList::because(\sprintf('Nobody offers "%s".', $offer));
    }

    /**
     * The offers and their owners, by identifier of the offer, checked for unique identifiers and for the limits on
     * offers.
     *
     * @param list<Participant> $participants
     *
     * @return array{array<array-key, Offer>, array<array-key, string>}
     */
    private function index(array $participants, Limits $limits): array
    {
        $offers = [];
        $owners = [];
        $seen = [];

        foreach ($participants as $participant) {
            if (isset($seen[$participant->id])) {
                throw InvalidTradeList::because(\sprintf('Two participants are identified by "%s".', $participant->id));
            }

            $seen[$participant->id] = true;

            if (\count($participant->offers) > $limits->offersPerParticipant) {
                throw LimitExceeded::because(\sprintf('The participant "%s" makes more than %d offers.', $participant->id, $limits->offersPerParticipant));
            }

            foreach ($participant->offers as $offer) {
                if (isset($offers[$offer->id])) {
                    throw InvalidTradeList::because(\sprintf('Two offers are identified by "%s".', $offer->id));
                }

                $offers[$offer->id] = $offer;
                $owners[$offer->id] = $participant->id;
            }

            if (\count($offers) > $limits->offers) {
                throw LimitExceeded::because(\sprintf('The trade list holds more than %d offers.', $limits->offers));
            }
        }

        return [$offers, $owners];
    }

    /**
     * Checks that the wish names offers of other participants, within the size of a group.
     *
     * @return int the number of offers the wish names
     */
    private function checkWish(Offer $offer, Wish $wish, Limits $limits): int
    {
        $names = $wish->offers();

        if (\count($names) > $limits->groupSize) {
            throw LimitExceeded::because(\sprintf('A group of wishes of the offer "%s" names more than %d offers.', $offer->id, $limits->groupSize));
        }

        foreach ($names as $name) {
            $owner = $this->owners[$name] ?? throw InvalidTradeList::because(\sprintf('The offer "%s" wishes for "%s", which nobody offers.', $offer->id, $name));

            if ($owner === $this->owners[$offer->id]) {
                throw InvalidTradeList::because(\sprintf('The offer "%s" wishes for "%s", an offer of the same participant.', $offer->id, $name));
            }
        }

        return \count($names);
    }
}
