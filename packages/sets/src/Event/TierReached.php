<?php

declare(strict_types=1);

namespace Tilttek\Sets\Event;

use Tilttek\Sets\Completion;
use Tilttek\Sets\Reward;

/**
 * Told once, when a holder reaches a tier of a set for the first time: the completion of the tier, and the rewards the
 * tier declares, for the application to grant.
 */
final readonly class TierReached
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
