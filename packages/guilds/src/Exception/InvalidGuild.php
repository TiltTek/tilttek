<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a guild's name, capacity or metadata out of bounds, a goal's key, name or target out of
 * bounds, a contribution's points or key out of bounds, a limit out of bounds.
 */
final class InvalidGuild extends InvalidArgumentException implements GuildsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
