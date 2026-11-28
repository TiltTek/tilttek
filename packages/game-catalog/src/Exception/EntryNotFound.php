<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Exception;

use RuntimeException;

/**
 * No entry of the catalogue has the identifier.
 */
final class EntryNotFound extends RuntimeException implements CatalogException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No entry of the catalogue has the identifier %s.', $id));
    }
}
