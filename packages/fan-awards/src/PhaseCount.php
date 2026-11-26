<?php

declare(strict_types=1);

namespace Tilttek\FanAwards;

use Phil\Poll\Result;

/**
 * The count of one phase of a category: the nominees it was cast on, the result `phil/poll` counted — null when fewer
 * than two options left nothing to vote on —, and the nominees it advanced: the shortlist of the next phase, or the
 * winners of the final.
 */
final readonly class PhaseCount
{
    /**
     * @param list<Nominee> $candidates
     * @param list<Nominee> $advanced
     */
    public function __construct(
        public Phase $phase,
        public array $candidates,
        public ?Result $result,
        public array $advanced,
    ) {
    }
}
