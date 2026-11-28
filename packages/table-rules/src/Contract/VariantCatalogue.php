<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Contract;

/**
 * The rule variants a game system offers, which a version of the rules may choose from. Without a catalogue, any
 * identifier is a variant.
 */
interface VariantCatalogue
{
    /**
     * Whether the variant is one the game system offers; the system is null when the version names none.
     */
    public function has(?string $system, string $variant): bool;
}
