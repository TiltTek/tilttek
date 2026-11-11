<?php

declare(strict_types=1);

namespace Tilttek\Activity;

/**
 * What recording or cancelling did: the entry, and whether it is new or was already in the journal under the same
 * idempotency key.
 */
final readonly class Appended
{
    public function __construct(
        public Entry $entry,
        public bool $new,
    ) {
    }
}
