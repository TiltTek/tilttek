<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Contract;

use Tilttek\Encounter\Encounter;
use Tilttek\Encounter\Exception\EncounterConflict;

/**
 * Keeps each encounter at a revision.
 */
interface EncounterStore
{
    /**
     * The encounter, or null when none of the identifier is kept.
     */
    public function encounter(string $id): ?Encounter;

    /**
     * Keeps the encounter at the next revision and returns it as kept. An encounter at revision 0 is new; any other is
     * kept only when the store holds it at that revision.
     *
     * @throws EncounterConflict when the encounter is new and the store has one of the identifier, or the store holds
     *                           it at another revision; nothing is kept
     */
    public function save(Encounter $encounter): Encounter;

    /**
     * Removes the encounter, when the store holds it at its revision.
     *
     * @throws EncounterConflict when the store holds it at another revision, or not at all; nothing is removed
     */
    public function remove(Encounter $encounter): void;
}
