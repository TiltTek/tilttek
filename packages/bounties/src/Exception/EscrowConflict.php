<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Exception;

use RuntimeException;

/**
 * An operation of the escrow that contradicts what is held under its key: another payer or amount under the same key,
 * payouts over the amount held, or a hold settled one way and then the other.
 */
final class EscrowConflict extends RuntimeException implements BountiesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
