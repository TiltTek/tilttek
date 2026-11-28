<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Contract;

use Tilttek\InitiativeCards\Encounter;
use Tilttek\InitiativeCards\Exception\EncounterConflict;

/**
 * Keeps the encounters, each at a version.
 */
interface EncounterStore
{
    /**
     * The encounter, or null when none has the identifier.
     */
    public function encounter(string $id): ?Encounter;

    /**
     * Keeps the encounter at the next version, and returns it. An encounter at version 0 is new; any other is kept only
     * when the store holds it at that version.
     *
     * @throws EncounterConflict when the encounter is new and the store has one of its identifier, or the store holds it
     *                           at another version; nothing is kept
     */
    public function save(Encounter $encounter): Encounter;
}
