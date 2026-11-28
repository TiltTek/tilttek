<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Exception;

use RuntimeException;

/**
 * The entry or the correction changed, or was added, since it was read: the change is not kept, and is made again on
 * what is read anew.
 */
final class CatalogConflict extends RuntimeException implements CatalogException
{
    public static function changed(string $id): self
    {
        return new self(\sprintf('%s changed since it was read.', $id));
    }
}
