<?php

declare(strict_types=1);

namespace Tilttek\Rating\Contract;

use Tilttek\Rating\GameResult;

/**
 * Where the results of a game are recorded — a log of plays, the application's own tables —, read to replay the
 * ratings of the game from the start: after a correction, a deleted result, or a change of system or setting.
 */
interface PlaySource
{
    /**
     * Every result of the game, friendly ones included, in the order they were played.
     *
     * @return iterable<GameResult>
     */
    public function results(string $game): iterable;
}
