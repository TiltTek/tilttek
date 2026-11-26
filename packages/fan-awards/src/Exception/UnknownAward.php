<?php

declare(strict_types=1);

namespace Tilttek\FanAwards\Exception;

use OutOfBoundsException;

/**
 * No edition holds this key, or the edition holds no category of this key.
 */
final class UnknownAward extends OutOfBoundsException implements FanAwardsException
{
    public static function edition(string $key): self
    {
        return new self(\sprintf('No edition is known as "%s".', $key));
    }

    public static function category(string $edition, string $key): self
    {
        return new self(\sprintf('The edition "%s" holds no category "%s".', $edition, $key));
    }
}
