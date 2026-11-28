<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Exception;

use RuntimeException;

/**
 * The log does not allow the change: the member may not make it, the play already exists, or the game is hidden in
 * the catalogue; nothing is kept.
 */
final class PlayRefused extends RuntimeException implements PlayLogException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
