<?php

declare(strict_types=1);

namespace Tilttek\Sets\Exception;

use RuntimeException;

/**
 * A set, or a version of a set, that was never defined.
 */
final class UnknownSet extends RuntimeException implements SetsException
{
    public static function named(string $code, ?int $version = null): self
    {
        return new self(null === $version
            ? \sprintf('The set "%s" is not defined.', $code)
            : \sprintf('The set "%s" has no version %d.', $code, $version));
    }
}
