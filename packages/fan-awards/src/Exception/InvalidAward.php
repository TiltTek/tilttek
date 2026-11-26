<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: an edition, a category or a nominee out of bounds, a counting method that does not
 * count ranked ballots, a ballot that does not fit the rules of its phase.
 */
final class InvalidAward extends InvalidArgumentException implements FanAwardsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
