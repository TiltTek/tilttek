<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use Tilttek\Leaderboards\Exception\InvalidLeaderboard;

/**
 * A title a member holds while their score on a board in the period under way reaches a threshold — and, for a title of
 * the leader, while nobody ranks before them: *regular* for five days of activity out of the last seven, on a board that
 * counts the days over a sliding window; *local legend* of a game for the member who played it most in ninety days, on
 * a board ranked by object.
 *
 * `new Title('regular', 5)`, `new Title('local-legend', 1, leader: true)`.
 */
final readonly class Title
{
    public function __construct(
        public string $name,
        public int $threshold,
        public bool $leader = false,
    ) {
        Names::check($name, 'title');

        if ($threshold < 1 || $threshold > Score::MAX) {
            throw InvalidLeaderboard::because(\sprintf('A title is held from a score of 1 to %d; %d is not a threshold.', Score::MAX, $threshold));
        }
    }

    public function isHeldWith(Score $score): bool
    {
        return $score->value >= $this->threshold;
    }
}
