<?php

declare(strict_types=1);

namespace Tilttek\Generator\Exception;

use OutOfBoundsException;

/**
 * A list asked for by name that the catalog does not have.
 */
final class UnknownList extends OutOfBoundsException implements GeneratorException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No list is named "%s".', $name));
    }
}
