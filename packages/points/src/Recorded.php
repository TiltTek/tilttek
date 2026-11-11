<?php

declare(strict_types=1);

namespace Tilttek\Points;

/**
 * What writing a movement did: the entry, and whether it is new or was already in the ledger under the same key.
 */
final readonly class Recorded
{
    public function __construct(
        public LedgerEntry $entry,
        public bool $new,
    ) {
    }
}
