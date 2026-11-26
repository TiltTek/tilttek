<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Exception;

use OutOfBoundsException;

/**
 * No guild holds this reference, or no goal this key.
 */
final class UnknownGuild extends OutOfBoundsException implements GuildsException
{
    public static function named(string $reference): self
    {
        return new self(\sprintf('No guild is known as "%s".', $reference));
    }

    public static function goal(string $key): self
    {
        return new self(\sprintf('No goal is known as "%s".', $key));
    }
}
