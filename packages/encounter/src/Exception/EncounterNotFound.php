<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Exception;

use RuntimeException;

/**
 * The store holds no encounter of the identifier.
 */
final class EncounterNotFound extends RuntimeException implements EncounterException
{
    public static function of(string $id): self
    {
        return new self(\sprintf('There is no encounter %s.', $id));
    }
}
