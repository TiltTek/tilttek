<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Exception;

use RuntimeException;

/**
 * The encounter changed since it was read, or one of the identifier already exists: the change is not kept, and is made
 * again on what is read anew.
 */
final class EncounterConflict extends RuntimeException implements EncounterException
{
    public static function changed(string $id): self
    {
        return new self(\sprintf('The encounter %s changed since it was read.', $id));
    }

    public static function exists(string $id): self
    {
        return new self(\sprintf('The encounter %s already exists.', $id));
    }
}
