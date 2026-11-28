<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Exception;

use RuntimeException;

/**
 * No encounter has the identifier, or the encounter has no such combatant.
 */
final class EncounterNotFound extends RuntimeException implements InitiativeException
{
    public static function encounter(string $id): self
    {
        return new self(\sprintf('No encounter has the identifier %s.', $id));
    }

    public static function combatant(string $encounter, string $id): self
    {
        return new self(\sprintf('The encounter %s has no combatant %s.', $encounter, $id));
    }
}
