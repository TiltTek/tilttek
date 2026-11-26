<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

use Tilttek\MathTrade\Exception\InvalidTradeList;

/**
 * How large a trade list may be. The solver's time grows with the number of offers and of wished offers, so a list
 * out of bounds is refused before it is solved.
 *
 * Each limit is 1 to its ceiling; the defaults fit a convention's trade of a few thousand offers.
 */
final readonly class Limits
{
    public const int DEFAULT_PARTICIPANTS = 2_000;

    public const int DEFAULT_OFFERS = 5_000;

    public const int DEFAULT_OFFERS_PER_PARTICIPANT = 100;

    public const int DEFAULT_WISHES_PER_OFFER = 500;

    public const int DEFAULT_GROUP_SIZE = 100;

    public const int DEFAULT_WISHED_OFFERS = 250_000;

    public const int DEFAULT_JSON_BYTES = 8_388_608;

    /**
     * The ceiling of the number of participants, of offers and of offers per participant.
     */
    public const int MAX_OFFERS = 100_000;

    /**
     * The ceiling of the wishes per offer and of the size of a group.
     */
    public const int MAX_WISHES = 10_000;

    /**
     * The ceiling of the wished offers of the whole list, each offer of a group counted.
     */
    public const int MAX_WISHED_OFFERS = 5_000_000;

    /**
     * The ceiling of the length of a JSON document: 256 MiB.
     */
    public const int MAX_JSON_BYTES = 268_435_456;

    /**
     * @param int $participants         the participants of the list
     * @param int $offers               the offers of the list
     * @param int $offersPerParticipant the offers of one participant
     * @param int $wishesPerOffer       the lines of one offer's wish list, a group counted once
     * @param int $groupSize            the offers of one group of wishes
     * @param int $wishedOffers         the wished offers of the whole list, each offer of a group counted
     * @param int $jsonBytes            the length of a JSON document the list is read from
     *
     * @throws InvalidTradeList when a limit is out of its bounds
     */
    public function __construct(
        public int $participants = self::DEFAULT_PARTICIPANTS,
        public int $offers = self::DEFAULT_OFFERS,
        public int $offersPerParticipant = self::DEFAULT_OFFERS_PER_PARTICIPANT,
        public int $wishesPerOffer = self::DEFAULT_WISHES_PER_OFFER,
        public int $groupSize = self::DEFAULT_GROUP_SIZE,
        public int $wishedOffers = self::DEFAULT_WISHED_OFFERS,
        public int $jsonBytes = self::DEFAULT_JSON_BYTES,
    ) {
        $this->bound('participants', $participants, self::MAX_OFFERS);
        $this->bound('offers', $offers, self::MAX_OFFERS);
        $this->bound('offers per participant', $offersPerParticipant, self::MAX_OFFERS);
        $this->bound('wishes per offer', $wishesPerOffer, self::MAX_WISHES);
        $this->bound('offers per group', $groupSize, self::MAX_WISHES);
        $this->bound('wished offers', $wishedOffers, self::MAX_WISHED_OFFERS);
        $this->bound('bytes of a JSON document', $jsonBytes, self::MAX_JSON_BYTES);
    }

    private function bound(string $what, int $value, int $max): void
    {
        if ($value < 1 || $value > $max) {
            throw InvalidTradeList::because(\sprintf('The limit of %s is 1 to %d; it is %d.', $what, $max, $value));
        }
    }
}
