<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A document of the library's format, or an extract given to an importer, that cannot be read: the message names the
 * place in the document, `attributes[2].key`, and what is wrong there.
 */
final class InvalidDocument extends InvalidArgumentException implements RpgSystemsException
{
    public static function at(string $path, string $reason, ?Throwable $previous = null): self
    {
        return new self('' === $path ? ucfirst($reason) : \sprintf('%s: %s', $path, $reason), 0, $previous);
    }
}
