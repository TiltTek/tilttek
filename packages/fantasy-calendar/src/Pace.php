<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\InvalidCalendar;

/**
 * How fast the current date of a world moves: only when the game master moves it, or so many days of the world for so
 * many real days.
 */
final readonly class Pace
{
    /**
     * The largest number of days on either side of a pace.
     */
    public const int MAX = 1000;

    private function __construct(public int $worldDays, public int $realDays)
    {
    }

    /**
     * The date moves only when the game master moves it.
     */
    public static function manual(): self
    {
        return new self(0, 1);
    }

    /**
     * So many days of the world pass for so many real days: 1 for 1 keeps the world in step with ours.
     *
     * @throws InvalidCalendar when a number is out of its bounds
     */
    public static function of(int $worldDays, int $realDays = 1): self
    {
        Limits::between($worldDays, 1, self::MAX, 'The world days of a pace');
        Limits::between($realDays, 1, self::MAX, 'The real days of a pace');

        return new self($worldDays, $realDays);
    }

    public function isManual(): bool
    {
        return 0 === $this->worldDays;
    }
}
