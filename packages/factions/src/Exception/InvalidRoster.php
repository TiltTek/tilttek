<?php

declare(strict_types=1);

namespace Tilttek\Factions\Exception;

use InvalidArgumentException;

/**
 * A value of a roster, of one of its factions or of a call is out of its shape or its bounds, a standing is off the
 * scale, a relation or a membership names what the roster lacks, or a call names a faction the roster does not hold.
 */
final class InvalidRoster extends InvalidArgumentException implements FactionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
