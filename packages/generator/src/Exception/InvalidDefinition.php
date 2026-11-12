<?php

declare(strict_types=1);

namespace Tilttek\Generator\Exception;

use InvalidArgumentException;

/**
 * A list, a generator or a file that does not have the expected shape, with where it was found.
 */
final class InvalidDefinition extends InvalidArgumentException implements GeneratorException
{
    public static function at(string $location, string $problem): self
    {
        return new self(\sprintf('%s: %s', $location, $problem));
    }
}
