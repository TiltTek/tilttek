<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Contract;

use Tilttek\WargameArmy\Catalogue;

/**
 * Where the application keeps its catalogues — its own, invented, or under a licence it holds: the library never ships
 * one.
 */
interface CatalogueProvider
{
    /**
     * The current version of the catalogue of that code, or null when the application has none.
     */
    public function find(string $code): ?Catalogue;
}
