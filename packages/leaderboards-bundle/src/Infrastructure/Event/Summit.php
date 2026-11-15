<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Event;

use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\League\Movement;
use Tilttek\Leaderboards\Placing;

/**
 * How a member reached the top: first of a board when its period closed, in the lead of the period under way as their
 * score changed, or promoted into the highest tier of a league when its week was settled. The rules live here once,
 * for the event and for the screen that celebrates it.
 */
enum Summit: string
{
    case FirstPlace = 'first_place';
    case Lead = 'lead';
    case HighestTier = 'highest_tier';

    /**
     * Whether the line of a snapshot is a first place; ties shared may give several.
     */
    public static function ofPlacing(Placing $placing): ?self
    {
        return 1 === $placing->rank ? self::FirstPlace : null;
    }

    /**
     * Whether the membership, once settled, took its member into the highest of the tiers, given from the lowest.
     *
     * @param list<string> $tiers
     */
    public static function ofMembership(Membership $membership, array $tiers): ?self
    {
        $count = \count($tiers);

        return Movement::Promoted === $membership->movement && $count > 1 && $tiers[$count - 2] === $membership->tier ? self::HighestTier : null;
    }
}
