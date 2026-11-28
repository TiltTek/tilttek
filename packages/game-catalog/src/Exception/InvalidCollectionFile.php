<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Exception;

use InvalidArgumentException;

/**
 * A collection file cannot be read as a whole: over its size limit, without a header, or without the column of the
 * identifiers. A row that cannot be read is reported with its line, never thrown.
 */
final class InvalidCollectionFile extends InvalidArgumentException implements CatalogException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
