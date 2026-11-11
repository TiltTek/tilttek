<?php

declare(strict_types=1);

namespace Tilttek\Points;

/**
 * What erasing a member did: the movements between the member and the ledger's own accounts deleted, and the
 * movements between the member and another member kept, the erased member's side written as `erased:member`, since
 * they are the other member's points too.
 */
final readonly class Erasure
{
    public function __construct(
        public int $deleted = 0,
        public int $detached = 0,
    ) {
    }
}
