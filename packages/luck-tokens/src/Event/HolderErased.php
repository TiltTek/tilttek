<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Event;

use Phil\Resource\ResourceReference;

/**
 * A member erased from the ledgers: the pseudonym that took their place, as holder and as actor, and how many entries
 * were rewritten. It names neither the member nor a table, so that a listener writes it to a log or a queue as it is.
 * Dispatched once the entries are rewritten, never for a member no entry names — one erased already.
 */
final readonly class HolderErased
{
    public function __construct(
        public ResourceReference $pseudonym,
        public int $entries,
    ) {
    }
}
