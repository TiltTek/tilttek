<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Exception;

use InvalidArgumentException;

/**
 * A ballot holds a vote out of its scale, or more votes than a ballot keeps.
 */
final class InvalidVote extends InvalidArgumentException implements CatalogException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
