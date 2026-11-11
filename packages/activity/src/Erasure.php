<?php

declare(strict_types=1);

namespace Tilttek\Activity;

/**
 * What erasing a member did: the entries of their gestures deleted, and the entries of other members' gestures whose
 * object was that member, kept with their object forgotten.
 */
final readonly class Erasure
{
    public function __construct(
        public int $deleted = 0,
        public int $detached = 0,
    ) {
    }
}
