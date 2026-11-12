<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\League;

use Tilttek\Leaderboards\Standing;

/**
 * A member's division as they see it: their membership, the division ranked among its members — each by their choice
 * of appearance —, how many members it holds, and how many of the first go up and of the last go down at the end of
 * the week.
 */
final readonly class Division
{
    public function __construct(
        public Membership $membership,
        public Standing $standing,
        public int $members,
        public int $promoted,
        public int $relegated,
    ) {
    }

    /**
     * Where a line of that rank goes if the week ended now: up for the first, down for the last — a member of the
     * division without a score counts among the last —, nowhere for the others.
     */
    public function movementAt(int $rank): Movement
    {
        return match (true) {
            $rank <= $this->promoted => Movement::Promoted,
            $rank > $this->members - $this->relegated => Movement::Relegated,
            default => Movement::Stayed,
        };
    }
}
