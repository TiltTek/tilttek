<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Internal\Identifier;

/**
 * Someone who takes part in the trade, and the offers they make. The identifier is the application's: the library
 * keeps no name and no address, which the application shows once the trade is confirmed.
 */
final readonly class Participant
{
    /**
     * @param string      $id     the identifier of the participant, unique in the trade list
     * @param list<Offer> $offers the offers they make, one at least
     *
     * @throws InvalidTradeList when the identifier is out of bounds, or the participant makes no offer
     */
    public function __construct(
        public string $id,
        public array $offers,
    ) {
        Identifier::check($id, 'a participant');

        if ([] === $offers) {
            throw InvalidTradeList::because(\sprintf('The participant "%s" makes no offer.', $id));
        }
    }
}
