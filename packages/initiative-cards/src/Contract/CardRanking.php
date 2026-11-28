<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Contract;

use Tilttek\CardDeck\Face;

/**
 * How the faces of a deck order the combatants: which card acts before which, and which card shuffles the deck again
 * once its round is over.
 */
interface CardRanking
{
    /**
     * Whether the ranking knows the face. An encounter starts only on a deck whose every face the ranking knows.
     */
    public function ranks(Face $face): bool;

    /**
     * Below zero when the first face acts before the second, above zero when it acts after it, zero when they tie: the
     * combatants who tie keep their order of the previous round, or of their joining.
     */
    public function compare(Face $one, Face $other): int;

    /**
     * Whether dealing the face shuffles the discard pile back into the draw pile before the next round.
     */
    public function reshuffles(Face $face): bool;
}
