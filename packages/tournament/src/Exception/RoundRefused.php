<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Exception;

use LogicException;

/**
 * A round asked for too early or too late: the current round has results missing or conflicts to settle, the
 * tournament is over, or the round given is not the one expected.
 */
final class RoundRefused extends LogicException implements TournamentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
