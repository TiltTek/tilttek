<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Exception;

use InvalidArgumentException;

/**
 * A value out of its range or shape: an identifier, a stage, a scoring, a list of participants or of tie-breakers.
 */
final class InvalidTournament extends InvalidArgumentException implements TournamentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
