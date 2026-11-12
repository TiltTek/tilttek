<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\League;

/**
 * What the leagues tell the application: a week was settled, once per board and week, so that it congratulates the
 * members who went up or rewards them.
 */
interface LeagueObserver
{
    public function leagueSettled(Settlement $settlement): void;
}
