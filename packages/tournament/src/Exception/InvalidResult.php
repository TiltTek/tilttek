<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Exception;

use InvalidArgumentException;

/**
 * A result that does not fit its match: a match unknown, a bye, a draw in an elimination, mission points out of range.
 */
final class InvalidResult extends InvalidArgumentException implements TournamentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
