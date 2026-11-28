<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Exception;

use RuntimeException;

/**
 * A change the catalogue refuses as it stands: an identifier or a barcode another entry holds, a link that cannot be,
 * a correction already decided or that changes nothing, a limit reached.
 */
final class CatalogRefused extends RuntimeException implements CatalogException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
