<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Contract;

use Tilttek\DeckBuilder\Format;

/**
 * Where the application keeps the formats its decks are built for.
 */
interface FormatCatalog
{
    /**
     * The format of that code, or null when the catalog does not hold it.
     */
    public function find(string $code): ?Format;
}
