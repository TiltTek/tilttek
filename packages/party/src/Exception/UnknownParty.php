<?php

declare(strict_types=1);

namespace Tilttek\Party\Exception;

use OutOfBoundsException;

/**
 * No party holds this reference, no loot this key, or no note this key.
 */
final class UnknownParty extends OutOfBoundsException implements PartyException
{
    public static function named(string $reference): self
    {
        return new self(\sprintf('No party is known as "%s".', $reference));
    }

    public static function loot(string $party, string $key): self
    {
        return new self(\sprintf('The party "%s" holds no loot under the key "%s".', $party, $key));
    }

    public static function note(string $party, string $key): self
    {
        return new self(\sprintf('The party "%s" holds no note under the key "%s".', $party, $key));
    }
}
