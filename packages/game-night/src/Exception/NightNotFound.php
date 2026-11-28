<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Exception;

use RuntimeException;

/**
 * No night has the identifier.
 */
final class NightNotFound extends RuntimeException implements GameNightException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No game night has the identifier %s.', $id));
    }
}
