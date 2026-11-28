<?php

declare(strict_types=1);

namespace Tilttek\Factions\Exception;

use RuntimeException;

/**
 * The roster changed since it was read, or a new one has the campaign of a kept one: the change is not kept, and is
 * made again on what is read anew.
 */
final class RosterConflict extends RuntimeException implements FactionsException
{
    public static function changed(string $campaign): self
    {
        return new self(\sprintf('The roster of the campaign %s changed since it was read.', $campaign));
    }
}
