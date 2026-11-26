<?php

declare(strict_types=1);

namespace Tilttek\TurnClock;

use Tilttek\TurnClock\Exception\InvalidClock;
use Tilttek\TurnClock\Internal\Identifier;
use Tilttek\TurnClock\Internal\Millis;

/**
 * One player's time: their budget, what is left of it when their turn started — below zero when in overtime — the turns
 * they ended and the time they spent, and their vacations. Durations are in milliseconds.
 */
final readonly class PlayerClock
{
    /** The most vacations a player keeps declared. */
    public const int MAX_VACATIONS = 8;

    public int $remaining;

    /** @var list<Vacation> */
    public array $vacations;

    /**
     * @param int            $budget    the player's budget, 1 second to 31 days
     * @param ?int           $remaining what is left, the budget when null
     * @param list<Vacation> $vacations at most 8, none overlapping another, the earliest first
     *
     * @throws InvalidClock when the identifier is not one, a duration or count is out of its bounds, or vacations overlap
     */
    public function __construct(
        public string $id,
        public int $budget,
        ?int $remaining = null,
        public int $turns = 0,
        public int $spent = 0,
        array $vacations = [],
    ) {
        Identifier::check($id, 'a player');
        TimeControl::checkBudget($budget);
        if ($turns < 0 || $spent < 0) {
            throw InvalidClock::because('The turns and the time spent are 0 or more.');
        }

        if (\count($vacations) > self::MAX_VACATIONS) {
            throw InvalidClock::because(\sprintf('A player keeps at most %d vacations.', self::MAX_VACATIONS));
        }

        usort($vacations, static fn (Vacation $one, Vacation $other): int => Millis::of($one->from) <=> Millis::of($other->from));
        for ($index = 1, $count = \count($vacations); $index < $count; ++$index) {
            if (Millis::of($vacations[$index]->from) < Millis::of($vacations[$index - 1]->until)) {
                throw InvalidClock::because("A player's vacations do not overlap.");
            }
        }

        $this->remaining = $remaining ?? $budget;
        $this->vacations = $vacations;
    }

    /**
     * The milliseconds of the span the player was not on vacation.
     */
    public function counted(int $from, int $until): int
    {
        $counted = max(0, $until - $from);
        foreach ($this->vacations as $vacation) {
            $counted -= $vacation->overlap($from, $until);
        }

        return $counted;
    }

    public function withRemaining(int $remaining): self
    {
        return new self($this->id, $this->budget, $remaining, $this->turns, $this->spent, $this->vacations);
    }

    /**
     * The player once the time of a turn is charged: what is left, the time it took, and one more turn when it ended.
     */
    public function charged(int $remaining, int $spent, bool $ended): self
    {
        return new self($this->id, $this->budget, $remaining, $ended ? $this->turns + 1 : $this->turns, $this->spent + $spent, $this->vacations);
    }

    /**
     * @param list<Vacation> $vacations
     */
    public function withVacations(array $vacations): self
    {
        return new self($this->id, $this->budget, $this->remaining, $this->turns, $this->spent, $vacations);
    }
}
