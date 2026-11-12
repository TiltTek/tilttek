<?php

declare(strict_types=1);

namespace Tilttek\Weather\Exception;

use InvalidArgumentException;

/**
 * A state that names what the climates do not declare: a zone, a season the zone does not have, a condition, or a
 * language the labels are not written in.
 */
final class UnknownClimate extends InvalidArgumentException implements WeatherException
{
    /**
     * @param list<string> $known
     */
    public static function of(string $kind, string $name, array $known): self
    {
        return new self(\sprintf('The %s "%s" is not declared; the climates know "%s".', $kind, $name, implode('", "', $known)));
    }
}
