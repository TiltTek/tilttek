<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A document of the library's format that cannot be read: the message names the place in the document,
 * `tables[2].rows[4].range`, and what is wrong there.
 */
final class InvalidDocument extends InvalidArgumentException implements ReferenceTablesException
{
    public static function at(string $path, string $reason, ?Throwable $previous = null): self
    {
        return new self('' === $path ? ucfirst($reason) : \sprintf('%s: %s', $path, $reason), 0, $previous);
    }
}
