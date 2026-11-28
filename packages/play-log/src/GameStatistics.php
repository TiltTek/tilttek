<?php

declare(strict_types=1);

namespace Tilttek\PlayLog;

use DateTimeImmutable;

/**
 * What the plays of one game add up to.
 */
final readonly class GameStatistics
{
    /**
     * @param string             $game         the identifier of the game in the catalogue
     * @param int                $plays        the plays of the game
     * @param ?int               $wins         the plays the member won, or null for a group
     * @param int                $minutes      the minutes of the plays whose length is known
     * @param ?DateTimeImmutable $lastPlayed   the latest date of a play, or null when no date is known
     * @param ?int               $highestScore the highest score of the member's seats, or of every confirmed seat for a group
     */
    public function __construct(
        public string $game,
        public int $plays,
        public ?int $wins,
        public int $minutes,
        public ?DateTimeImmutable $lastPlayed,
        public ?int $highestScore,
    ) {
    }
}
