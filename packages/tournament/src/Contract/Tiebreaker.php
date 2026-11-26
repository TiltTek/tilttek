<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Contract;

use Tilttek\Tournament\Records;

/**
 * A tie-breaker: a score for each participant of a group tied on points, the highest first. The group is given whole,
 * so that a tie-breaker that reads only the matches inside it — the head-to-head — is computed again on each smaller
 * group left tied.
 *
 * A tie-breaker keeps nothing between calls.
 */
interface Tiebreaker
{
    /**
     * The stable code a tournament declares the tie-breaker by: letters, digits and "-".
     */
    public function id(): string;

    /**
     * The score of each participant of the group, by participant.
     *
     * @param list<string> $tied the participants tied, two at least
     *
     * @return array<string, int>
     */
    public function scores(array $tied, Records $records): array;
}
