<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

use Tilttek\WritingGoals\Exception\InvalidGoal;

/**
 * The days an author writes: days of the week, less the dates they set aside. The other days have no quota.
 */
final readonly class WritingDays
{
    public const int MAX_EXCLUDED = 366;

    /**
     * @param list<int>       $weekdays ISO days of the week, 1 for Monday to 7 for Sunday, in order
     * @param array<int, Day> $excluded the days set aside, by their numbers
     */
    private function __construct(
        public array $weekdays,
        private array $excluded,
    ) {
    }

    public static function everyDay(): self
    {
        return new self([1, 2, 3, 4, 5, 6, 7], []);
    }

    /**
     * @param int ...$weekdays ISO days of the week, 1 for Monday to 7 for Sunday
     */
    public static function on(int ...$weekdays): self
    {
        foreach ($weekdays as $weekday) {
            if ($weekday < 1 || $weekday > 7) {
                throw InvalidGoal::because(\sprintf('A day of the week is 1, Monday, to 7, Sunday; %d is not one.', $weekday));
            }
        }

        $weekdays = array_unique($weekdays);
        sort($weekdays);

        if ([] === $weekdays) {
            throw InvalidGoal::because('An author writes on one day of the week at least.');
        }

        return new self($weekdays, []);
    }

    /**
     * The same days, less some dates: a holiday, a trip.
     */
    public function except(Day ...$days): self
    {
        $excluded = $this->excluded;

        foreach ($days as $day) {
            $excluded[$day->number] = $day;
        }

        if (\count($excluded) > self::MAX_EXCLUDED) {
            throw InvalidGoal::because(\sprintf('An author sets aside %d dates at most.', self::MAX_EXCLUDED));
        }

        return new self($this->weekdays, $excluded);
    }

    public function includes(Day $day): bool
    {
        return !isset($this->excluded[$day->number]) && \in_array($day->weekday(), $this->weekdays, true);
    }

    /**
     * @return list<string> the dates set aside, in order
     */
    public function excluded(): array
    {
        $excluded = $this->excluded;
        ksort($excluded);

        return array_values(array_map(static fn (Day $day): string => $day->toString(), $excluded));
    }
}
