<?php

declare(strict_types=1);

namespace Tilttek\MathTrade;

use Tilttek\MathTrade\Contract\Wish;
use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Internal\Identifier;

/**
 * A copy of a game a participant puts in the trade, and the offers they would take for it, most wished first.
 *
 * The game names what the copy is: two copies of one game share it. A participant who offers several copies and wishes
 * for a game of which several copies are offered receives that game once at most. Without a game, the offer is a game
 * of its own.
 */
final readonly class Offer
{
    /**
     * @param string     $id    the identifier of the offer, unique in the trade list
     * @param list<Wish> $wants the wishes, most wished first: the first has priority 1
     * @param ?string    $game  the identifier of the game the copy is, shared by its other copies
     *
     * @throws InvalidTradeList when an identifier is out of bounds, or a wish names an offer twice or the offer itself
     */
    public function __construct(
        public string $id,
        public array $wants = [],
        public ?string $game = null,
    ) {
        Identifier::check($id, 'an offer');

        if (null !== $game) {
            Identifier::check($game, 'a game');
        }

        $seen = [];

        foreach ($wants as $wish) {
            foreach ($wish->offers() as $offer) {
                if ($offer === $id) {
                    throw InvalidTradeList::because(\sprintf('The offer "%s" wishes for itself.', $id));
                }

                if (isset($seen[$offer])) {
                    throw InvalidTradeList::because(\sprintf('The offer "%s" wishes for the offer "%s" twice.', $id, $offer));
                }

                $seen[$offer] = true;
            }
        }
    }

    /**
     * The priority of the wished offer, 1 for the first line of the wish list: the offers of a group share theirs. Null
     * when the offer does not wish for it.
     *
     * @return ?positive-int
     */
    public function priorityOf(string $offer): ?int
    {
        foreach ($this->wants as $index => $wish) {
            if (\in_array($offer, $wish->offers(), true)) {
                return $index + 1;
            }
        }

        return null;
    }
}
