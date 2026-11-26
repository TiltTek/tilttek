<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards\Exception;

use InvalidArgumentException;

/**
 * A presence the library refuses: an event, a place or a track that does not fit in the context of an activity.
 */
final class InvalidPresence extends InvalidArgumentException implements CheckInRewardsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
