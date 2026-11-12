<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

use Tilttek\WritingGoals\Exception\InvalidGoal;

/**
 * What an author wrote toward a goal, day by day.
 */
final readonly class Progress
{
    public const int MAX_PER_DAY = Goal::MAX_TARGET;

    /**
     * @param array<int, int> $counts the count of each day, by the day's number
     */
    private function __construct(
        private array $counts,
    ) {
    }

    public static function none(): self
    {
        return new self([]);
    }

    /**
     * @param array<string, int> $counts the count of each day, by its date written `YYYY-MM-DD`
     */
    public static function fromArray(array $counts): self
    {
        $progress = self::none();

        foreach ($counts as $date => $count) {
            $progress = $progress->with(Day::fromString($date), $count);
        }

        return $progress;
    }

    /**
     * The same progress with the count of one day replaced.
     */
    public function with(Day $day, int $count): self
    {
        if ($count < 0 || $count > self::MAX_PER_DAY) {
            throw InvalidGoal::because(\sprintf('A day counts 0 to %d; %d is not one.', self::MAX_PER_DAY, $count));
        }

        $counts = $this->counts;
        $counts[$day->number] = $count;

        return new self($counts);
    }

    public function on(Day $day): int
    {
        return $this->counts[$day->number] ?? 0;
    }

    public function before(Day $day): int
    {
        $total = 0;

        foreach ($this->counts as $number => $count) {
            if ($number < $day->number) {
                $total += $count;
            }
        }

        return $total;
    }

    public function total(): int
    {
        return array_sum($this->counts);
    }

    /**
     * @return array<string, int> the count of each day, by its date, in order
     */
    public function toArray(): array
    {
        $counts = $this->counts;
        ksort($counts);
        $array = [];

        foreach ($counts as $number => $count) {
            $array[Day::fromNumber($number)->toString()] = $count;
        }

        return $array;
    }
}
