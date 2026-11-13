<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Contract;

/**
 * The cards one collection owns — a member's shelf, a club's boxes —, as the application knows them: the library
 * keeps no collection.
 */
interface OwnedCards
{
    /**
     * The copies of the card the collection owns; 0 when it owns none.
     */
    public function copiesOf(string $card): int;
}
