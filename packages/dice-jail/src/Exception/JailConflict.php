<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Exception;

use RuntimeException;

/**
 * Two writers changed the record of the same die at once: the store expected another position for the event.
 */
final class JailConflict extends RuntimeException implements DiceJailException
{
    public static function at(string $die, int $expected, int $given): self
    {
        return new self(\sprintf('The record of the die %s expects position %d; an event came at position %d.', $die, $expected, $given));
    }
}
