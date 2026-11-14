<?php

declare(strict_types=1);

namespace Tilttek\Rating\Exception;

use DomainException;

/**
 * A result recorded a second time: a double click, or a message delivered again. The ratings already count it, and
 * nothing more is kept.
 */
final class ResultAlreadyRecorded extends DomainException implements RatingException
{
    public static function of(string $game, string $result): self
    {
        return new self(\sprintf('The result %s of the game %s is recorded already.', $result, $game));
    }
}
