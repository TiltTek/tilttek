<?php

declare(strict_types=1);

namespace Tilttek\Points\Exception;

use RuntimeException;
use Tilttek\Points\Account;

/**
 * Other movements of the account kept being written while its expiry was computed, each attempt read again: nothing
 * was expired by the last attempt, and the operation may be asked again.
 */
final class LedgerContention extends RuntimeException implements PointsException
{
    public static function on(Account $account, string $currency, int $attempts): self
    {
        return new self(\sprintf('The account "%s" changed during each of %d attempts to expire its %s.', $account->toString(), $attempts, $currency));
    }
}
