<?php

declare(strict_types=1);

namespace Tilttek\Sets\Event;

use Tilttek\Sets\Completion;
use Tilttek\Sets\Reward;

/**
 * Told once, when a holder completes a set for the first time: the completion, and the rewards the version of the set
 * declares, for the application to grant.
 */
final readonly class SetCompleted
{
    /**
     * @param list<Reward> $rewards
     */
    public function __construct(
        public Completion $completion,
        public array $rewards,
    ) {
    }
}
