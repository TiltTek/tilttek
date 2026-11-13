<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Exception;

use RuntimeException;

/**
 * A placement that does not exist, or no longer does.
 */
final class UnknownPlacement extends RuntimeException implements StickersException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('There is no placement "%s".', substr($id, 0, 64)));
    }
}
