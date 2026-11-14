<?php

declare(strict_types=1);

namespace Tilttek\Badges\Exception;

use RuntimeException;

/**
 * A badge awarded by hand whose number other awards, made at the same time, took at every attempt.
 */
final class AwardConflict extends RuntimeException implements BadgesException
{
    public static function after(string $code, string $member, int $attempts): self
    {
        return new self(\sprintf('The badge "%s" could not be awarded by hand to %s: other awards took its number %d times in a row.', $code, $member, $attempts));
    }
}
