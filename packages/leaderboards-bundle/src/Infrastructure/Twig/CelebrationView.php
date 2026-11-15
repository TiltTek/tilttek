<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Twig;

use Tilttek\Leaderboards\Board;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\Summit;

/**
 * What a member's celebration says: the board, the period or week that closed, how they reached the top, the tier they
 * reached — the highest, on a league —, and the effect the configuration chose.
 */
final readonly class CelebrationView
{
    public function __construct(
        public Board $board,
        public string $period,
        public Summit $summit,
        public string $effect,
        public ?string $tier = null,
    ) {
    }
}
