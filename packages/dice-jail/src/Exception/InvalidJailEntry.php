<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Exception;

use InvalidArgumentException;

/**
 * A keeper, a die or a reason the jail does not take: empty, too long, holding control characters, or a keeper written
 * as an email address rather than a stable identifier.
 */
final class InvalidJailEntry extends InvalidArgumentException implements DiceJailException
{
    public static function identifier(string $what, int $limit): self
    {
        return new self(\sprintf('The %s is a text of 1 to %d bytes, in UTF-8, without control characters.', $what, $limit));
    }

    public static function emailKeeper(): self
    {
        return new self('The keeper is a stable identifier of the member, never an email address.');
    }

    public static function reason(int $limit): self
    {
        return new self(\sprintf('A reason holds at most %d characters, in UTF-8, without control characters but line breaks.', $limit));
    }
}
