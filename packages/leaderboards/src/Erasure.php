<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

/**
 * What erasing a member removed: their contributions and scores, their lines in the snapshots, their choice of
 * appearance, and their tiers and memberships in the leagues.
 */
final readonly class Erasure
{
    public function __construct(
        public int $scores = 0,
        public int $placings = 0,
        public int $appearances = 0,
        public int $leagues = 0,
    ) {
    }
}
