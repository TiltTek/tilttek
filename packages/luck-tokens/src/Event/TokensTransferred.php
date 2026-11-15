<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Event;

use Tilttek\LuckTokens\Ledger\LedgerEntry;

/**
 * Tokens passed from one holder to another, both entries written together. Dispatched once they are in the ledger,
 * never for an operation sent again.
 */
final readonly class TokensTransferred
{
    public function __construct(
        public LedgerEntry $out,
        public LedgerEntry $in,
    ) {
    }

    /**
     * How many tokens: always positive.
     */
    public function amount(): int
    {
        return $this->in->change;
    }
}
