<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Vote;

/**
 * The votes one game proposed received.
 */
final readonly class Tally
{
    /**
     * @param int $percent the share of the votes cast, 0 to 100, rounded half up
     */
    public function __construct(public string $game, public int $votes, public int $percent)
    {
    }
}
