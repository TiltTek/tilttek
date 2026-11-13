<?php

declare(strict_types=1);

namespace Tilttek\Heraldry\Exception;

use InvalidArgumentException;

/**
 * A seed that is not 64 hexadecimal digits.
 */
final class InvalidSeed extends InvalidArgumentException implements HeraldryException
{
    public static function notHex(): self
    {
        return new self('A seed is written as 64 hexadecimal digits: the 32 bytes it is made of.');
    }
}
