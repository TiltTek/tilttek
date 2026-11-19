<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: an identifier, an entrant, a number of winners, a beacon chain or a relay it cannot
 * take, or a raffle or a proof that does not read.
 */
final class InvalidRaffle extends InvalidArgumentException implements RaffleException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
