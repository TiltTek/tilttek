<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Store;

use Tilttek\MiniGames\Contract\ScoreSink;
use Tilttek\MiniGames\DailyScore;

/**
 * Keeps the scores in memory, in the order they came: for tests.
 */
final class InMemoryScoreSink implements ScoreSink
{
    /** @var list<DailyScore> */
    private array $scores = [];

    public function record(DailyScore $score): void
    {
        $this->scores[] = $score;
    }

    /**
     * @return list<DailyScore>
     */
    public function scores(): array
    {
        return $this->scores;
    }
}
