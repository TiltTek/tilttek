<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Exception;

use RuntimeException;

/**
 * An army list names a catalogue the application does not provide.
 */
final class UnknownCatalogue extends RuntimeException implements WargameArmyException
{
    public static function named(string $catalogue): self
    {
        return new self(\sprintf('The catalogue "%s" is not provided.', $catalogue));
    }
}
