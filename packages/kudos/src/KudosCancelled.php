<?php

declare(strict_types=1);

namespace Tilttek\Kudos;

/**
 * Thanks were cancelled — withdrawn by their giver, or removed by a moderator —: identifiers only, the key, the giver
 * and the receiver, null once erased.
 */
final readonly class KudosCancelled
{
    public function __construct(
        public string $key,
        public string $giver,
        public ?string $receiver,
        public bool $byGiver,
    ) {
    }
}
