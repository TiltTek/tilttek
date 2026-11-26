<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Store;

use Tilttek\MiniGames\Contract\ScoreSink;
use Tilttek\MiniGames\DailyScore;

/**
 * Keeps no score: the sink of an application that ranks nobody.
 */
final class NullScoreSink implements ScoreSink
{
    public function record(DailyScore $score): void
    {
    }
}
