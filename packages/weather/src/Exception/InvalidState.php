<?php

declare(strict_types=1);

namespace Tilttek\Weather\Exception;

use InvalidArgumentException;

/**
 * A state of the weather that cannot be read: a zone or a season missing, or a temperature that is not a whole number
 * of degrees.
 */
final class InvalidState extends InvalidArgumentException implements WeatherException
{
    public static function of(string $problem): self
    {
        return new self(\sprintf('The state of the weather %s.', $problem));
    }
}
