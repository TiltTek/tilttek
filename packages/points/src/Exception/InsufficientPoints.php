<?php

declare(strict_types=1);

namespace Tilttek\Points\Exception;

use RuntimeException;
use Tilttek\Points\Account;

/**
 * A spending or a transfer larger than the balance it is paid from: a balance never becomes negative by an exchange.
 */
final class InsufficientPoints extends RuntimeException implements PointsException
{
    public static function in(Account $account, string $currency, int $balance, int $amount): self
    {
        return new self(\sprintf('The account "%s" holds %d %s; %d cannot be paid from it.', $account->toString(), $balance, $currency, $amount));
    }
}
