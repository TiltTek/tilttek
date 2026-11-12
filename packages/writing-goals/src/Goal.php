<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

use DateTimeZone;
use Exception;
use Phil\Resource\ResourceReference;
use Tilttek\WritingGoals\Exception\InvalidGoal;

/**
 * An author's writing goal: a target in words or pages, from a first day to a deadline, both included, the curve its
 * plan follows, the days the author writes, and the timezone their days are counted in.
 */
final readonly class Goal
{
    public const int MAX_TARGET = 1_000_000_000;

    public const int MAX_DAYS = 3660;

    public string $id;

    public int $target;

    public Day $start;

    public Day $deadline;

    public Curve $curve;

    public WritingDays $writingDays;

    public DateTimeZone $timezone;

    /**
     * @var list<Day>
     */
    private array $plannedDays;

    /**
     * @param string $id       1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter or a digit
     * @param int    $target   1 to 1 000 000 000
     * @param string $timezone the author's timezone, `America/Montreal`
     */
    public function __construct(
        string $id,
        public ResourceReference $author,
        int $target,
        Day $start,
        Day $deadline,
        public Unit $unit = Unit::Words,
        ?Curve $curve = null,
        ?WritingDays $writingDays = null,
        string $timezone = 'UTC',
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $id)) {
            throw InvalidGoal::because(\sprintf('A goal identifier is 1 to 128 letters, digits, "_", ".", ":" or "-", starting with a letter or a digit; "%s" is not.', $id));
        }

        if ($target < 1 || $target > self::MAX_TARGET) {
            throw InvalidGoal::because(\sprintf('A target is 1 to %d; %d is not one.', self::MAX_TARGET, $target));
        }

        $span = $start->daysUntil($deadline) + 1;

        if ($span < 1 || $span > self::MAX_DAYS) {
            throw InvalidGoal::because(\sprintf('A goal lasts 1 to %d days, its first and last included; from %s to %s is not one.', self::MAX_DAYS, $start->toString(), $deadline->toString()));
        }

        try {
            $this->timezone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidGoal::because(\sprintf('"%s" is not a timezone.', $timezone));
        }

        $this->id = $id;
        $this->target = $target;
        $this->start = $start;
        $this->deadline = $deadline;
        $this->curve = $curve ?? Curve::linear();
        $this->writingDays = $writingDays ?? WritingDays::everyDay();

        $this->plannedDays = $this->plannedDays();

        if ([] === $this->plannedDays) {
            throw InvalidGoal::because(\sprintf('A goal from %s to %s has no writing day.', $start->toString(), $deadline->toString()));
        }
    }

    /**
     * Every writing day of the plan, in order.
     *
     * @return list<Day>
     */
    public function writingDaysOfPlan(): array
    {
        return $this->plannedDays;
    }

    /**
     * @return list<Day>
     */
    private function plannedDays(): array
    {
        $days = [];

        for ($day = $this->start; !$day->isAfter($this->deadline); $day = $day->plus(1)) {
            if ($this->writingDays->includes($day)) {
                $days[] = $day;
            }
        }

        return $days;
    }
}
