<?php

declare(strict_types=1);

namespace Tilttek\Points\Ledger;

use Tilttek\Points\Exception\InvalidPoints;

/**
 * The bounds of a read that every ledger checks the same way.
 */
final class Reading
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidPoints when the position is negative or the limit is below 1
     */
    public static function check(int $after, int $limit): void
    {
        if ($after < 0) {
            throw InvalidPoints::because(\sprintf('A read starts after a position of 0 or more; %d is not one.', $after));
        }

        if ($limit < 1) {
            throw InvalidPoints::because(\sprintf('A read takes at least one entry; %d is not a limit.', $limit));
        }
    }
}
