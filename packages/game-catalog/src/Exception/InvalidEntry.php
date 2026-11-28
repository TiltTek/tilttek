<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Exception;

use InvalidArgumentException;

/**
 * A value of an entry, an identifier, a link, an erratum or a barcode is out of its shape or its bounds.
 */
final class InvalidEntry extends InvalidArgumentException implements CatalogException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
