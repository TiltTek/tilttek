<?php

declare(strict_types=1);

namespace Tilttek\Collections;

/**
 * What is told when a member completes a set for the first time: the set, with the rewards it declares, and when.
 */
final readonly class SetCompleted
{
    public function __construct(
        public ItemSet $set,
        public SetCompletion $completion,
    ) {
    }
}
