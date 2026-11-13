<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Contract;

use Tilttek\DeckBuilder\Card;

/**
 * Where the application keeps its cards — its own, invented, or under a licence it holds: the library never ships a
 * catalog.
 */
interface CardCatalog
{
    /**
     * The card of that identifier, or null when the catalog does not hold it.
     */
    public function find(string $id): ?Card;
}
