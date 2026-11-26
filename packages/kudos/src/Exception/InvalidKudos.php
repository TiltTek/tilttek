<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a message name or text out of bounds, two messages with one name, limits out of bounds,
 * an object whose reference is too long to be kept, a read out of bounds, an activity read as thanks that holds no
 * message.
 */
final class InvalidKudos extends InvalidArgumentException implements KudosException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
