<?php

declare(strict_types=1);

namespace Tilttek\Weather\Exception;

use InvalidArgumentException;

/**
 * Climates that cannot be used: a document that does not parse, a value of the wrong kind or out of its range, a name
 * that is not an identifier, a label missing for a language, a condition or a season that is not declared. The message
 * starts with the place of the problem: `zones.temperate.seasons.spring.low: …`.
 */
final class InvalidClimate extends InvalidArgumentException implements WeatherException
{
    public static function at(string $place, string $problem): self
    {
        return new self(\sprintf('%s: %s', $place, $problem));
    }
}
