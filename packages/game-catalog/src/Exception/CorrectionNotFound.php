<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Exception;

use RuntimeException;

/**
 * No correction has the identifier.
 */
final class CorrectionNotFound extends RuntimeException implements CatalogException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No correction has the identifier %s.', $id));
    }
}
