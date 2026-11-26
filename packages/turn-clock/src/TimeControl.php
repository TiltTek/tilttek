<?php

declare(strict_types=1);

namespace Tilttek\TurnClock;

use Tilttek\TurnClock\Exception\InvalidClock;

/**
 * The time control of a clock: each player's budget, the increment and how it gives time back, and what happens when a
 * budget runs out. Durations are in milliseconds.
 */
final readonly class TimeControl
{
    /** The shortest budget: one second. */
    public const int MIN_BUDGET = 1_000;

    /** The longest budget: 31 days. */
    public const int MAX_BUDGET = 31 * 86_400_000;

    /** The longest increment: one day. */
    public const int MAX_INCREMENT = 86_400_000;

    /**
     * @param int $budget    each player's budget, 1 second to 31 days
     * @param int $increment the increment, 0 to 1 day; unused by `Mode::PerTurn`
     *
     * @throws InvalidClock when a duration is out of its bounds
     */
    public function __construct(
        public int $budget,
        public Mode $mode = Mode::Fischer,
        public int $increment = 0,
        public Overtime $overtime = Overtime::Continue,
    ) {
        self::checkBudget($budget);
        if ($increment < 0 || $increment > self::MAX_INCREMENT) {
            throw InvalidClock::because('An increment is 0 to 1 day.');
        }
    }

    /**
     * Days per move, as in a game by correspondence: each turn has the days again, and a player out of them is out.
     *
     * @param int $days 1 to 31
     *
     * @throws InvalidClock when the days are out of their bounds
     */
    public static function daysPerTurn(int $days): self
    {
        return new self($days * 86_400_000, Mode::PerTurn, 0, Overtime::Forfeit);
    }

    /**
     * @throws InvalidClock when the budget is out of its bounds
     */
    public static function checkBudget(int $budget): int
    {
        if ($budget < self::MIN_BUDGET || $budget > self::MAX_BUDGET) {
            throw InvalidClock::because('A budget is 1 second to 31 days.');
        }

        return $budget;
    }
}
