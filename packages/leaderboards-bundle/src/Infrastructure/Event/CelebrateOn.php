<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Event;

/**
 * When a first place is celebrated, as the site chooses: once the period closed, for who finished first of its
 * snapshot; or as a score changes, for who takes the lead of the period under way. A promotion into the highest tier
 * of a league is celebrated when its week is settled, either way.
 */
enum CelebrateOn: string
{
    case PeriodClose = 'period_close';
    case ScoreChange = 'score_change';
}
