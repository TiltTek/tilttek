<?php

declare(strict_types=1);

namespace Tilttek\Rating\Exception;

use DomainException;

/**
 * A request the state of the ratings does not allow: ratings computed by another system, or a replay without a source
 * of results.
 */
final class RatingRefused extends DomainException implements RatingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
