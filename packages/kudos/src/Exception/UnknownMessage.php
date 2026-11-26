<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Exception;

use InvalidArgumentException;

/**
 * A message the application's list does not hold: thanks are given with one of its messages, never with a free text.
 */
final class UnknownMessage extends InvalidArgumentException implements KudosException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('"%s" is not one of the messages thanks are given with.', $name));
    }
}
