<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Exception;

use RuntimeException;

/**
 * No play has the identifier.
 */
final class PlayNotFound extends RuntimeException implements PlayLogException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No play has the identifier %s.', $id));
    }
}
