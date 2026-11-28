<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Exception;

use RuntimeException;

/**
 * The night does not allow the change now: it is cancelled, not published, started or full, or the member is not
 * signed up; nothing is kept.
 */
final class NightRefused extends RuntimeException implements GameNightException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
