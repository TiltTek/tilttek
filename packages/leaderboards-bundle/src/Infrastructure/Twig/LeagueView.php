<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Twig;

use DateTimeImmutable;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\League\Division;
use Tilttek\Leaderboards\League\Membership;

/**
 * What a member's league screen shows: the board, its tiers from the lowest, the member's tier, their division this
 * week — null before they score —, when the week ends, where they went last week, and how they appear to the members
 * of their division.
 */
final readonly class LeagueView
{
    /**
     * @param list<string> $tiers from the lowest to the highest
     */
    public function __construct(
        public Board $board,
        public array $tiers,
        public string $tier,
        public ?Division $division,
        public ?DateTimeImmutable $weekEndsAt,
        public ?Membership $lastWeek,
        public Appearance $appearance,
    ) {
    }

    /**
     * The tiers from the highest down, as a ladder shows them.
     *
     * @return list<string>
     */
    public function ladder(): array
    {
        return array_reverse($this->tiers);
    }
}
