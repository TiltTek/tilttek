<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Contract;

use Tilttek\Conditions\CharacterConditions;
use Tilttek\Conditions\Exception\ConditionConflict;

/**
 * Keeps the conditions of each character, at a version.
 */
interface ConditionStore
{
    /**
     * The character's conditions, or null when none were ever kept.
     */
    public function conditions(string $character): ?CharacterConditions;

    /**
     * Keeps the conditions at the next version, and returns them. Conditions at version 0 are new; any others are kept
     * only when the store holds them at that version.
     *
     * @throws ConditionConflict when they are new and the store has some for the character, or the store holds them at
     *                           another version; nothing is kept
     */
    public function save(CharacterConditions $conditions): CharacterConditions;
}
