<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Exception;

use RuntimeException;

/**
 * Two writers changed a raffle at once: the version stored is not the one the change was made from, and the caller
 * reads the raffle again and retries; or the entrants the store keeps changed after the entries closed.
 */
final class RaffleConflict extends RuntimeException implements RaffleException
{
    public static function at(string $raffle, int $expected, ?int $stored): self
    {
        return new self(\sprintf('The raffle %s was changed from version %d, but the store holds %s.', $raffle, $expected, null === $stored ? 'none' : 'version '.$stored));
    }

    public static function entrantsChanged(string $raffle): self
    {
        return new self(\sprintf('The entrants the store keeps for the raffle %s no longer hash to the hash announced when its entries closed.', $raffle));
    }
}
