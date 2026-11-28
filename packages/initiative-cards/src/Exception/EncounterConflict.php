<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Exception;

use RuntimeException;

/**
 * The encounter changed since it was read, or a new one has the identifier of a kept one: the change is not kept, and
 * is made again on what is read anew.
 */
final class EncounterConflict extends RuntimeException implements InitiativeException
{
    public static function changed(string $id): self
    {
        return new self(\sprintf('The encounter %s changed since it was read.', $id));
    }
}
