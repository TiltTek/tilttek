<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Catalogue;

use Override;
use Tilttek\RpgSystems\Systems;
use Tilttek\TableRules\Contract\VariantCatalogue;

/**
 * The rule variants the game systems of `tilttek/rpg-systems` offer: a version may choose a variant of its system only
 * when the system holds it, by key, and no variant when it names no system or one the application does not offer.
 *
 * ```php
 * $tableRules = new TableRules($store, $clock, variants: new SystemVariantCatalogue($systems));
 * ```
 */
final readonly class SystemVariantCatalogue implements VariantCatalogue
{
    public function __construct(private Systems $systems)
    {
    }

    #[Override]
    public function has(?string $system, string $variant): bool
    {
        return null !== $system && $this->systems->has($system) && $this->systems->get($system)->hasVariant($variant);
    }
}
