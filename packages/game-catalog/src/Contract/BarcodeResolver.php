<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Contract;

use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\Gtin;

/**
 * A port that identifies a barcode the catalogue does not know yet: it returns the identifier of the game in an open
 * database, and the catalogue finds its entry by that identifier. It is asked once per barcode: the answer is kept,
 * and the next box of the same edition is identified locally.
 */
interface BarcodeResolver
{
    public function resolve(Gtin $barcode): ?ExternalId;
}
