<?php

declare(strict_types=1);

namespace Tilttek\PlayLog;

/**
 * What the plays of a member or of a group add up to, computed when asked from at most `PlayStatistics::MAX_PLAYS`
 * plays, the latest first.
 */
final readonly class Statistics
{
    /**
     * @param int                    $plays      the plays read
     * @param ?int                   $wins       the plays the member won, or null for a group
     * @param int                    $unfinished the plays left unfinished
     * @param int                    $minutes    the minutes of the plays whose length is known
     * @param int                    $timedPlays the plays whose length is known
     * @param list<GameStatistics>   $games      every game played, the most played first, then by identifier
     * @param list<PlayerStatistics> $players    the members played with most, or the members of the group, at most
     *                                           `PlayStatistics::MAX_PLAYERS`, the most plays first, then by identifier
     * @param int                    $hIndex     the most games played at least as many times as their number
     * @param bool                   $truncated  whether more plays matched than were read
     */
    public function __construct(
        public int $plays,
        public ?int $wins,
        public int $unfinished,
        public int $minutes,
        public int $timedPlays,
        public array $games,
        public array $players,
        public int $hIndex,
        public bool $truncated,
    ) {
    }

    /**
     * The average length of a play whose length is known, in whole minutes, or null when none is.
     */
    public function averageMinutes(): ?int
    {
        return 0 === $this->timedPlays ? null : intdiv($this->minutes, $this->timedPlays);
    }

    public function game(string $game): ?GameStatistics
    {
        foreach ($this->games as $statistics) {
            if ($game === $statistics->game) {
                return $statistics;
            }
        }

        return null;
    }
}
