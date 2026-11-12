<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Benchmark;

use Phil\Resource\ResourceReference;
use Tilttek\WritingGoals\Curve;
use Tilttek\WritingGoals\Day;
use Tilttek\WritingGoals\Goal;
use Tilttek\WritingGoals\Planner;
use Tilttek\WritingGoals\Progress;
use Tilttek\WritingGoals\WritingDays;

/**
 * The plan a page computes at each display: a month-long goal written every day, and a goal over the longest span,
 * ten years, on weekdays with a front-loaded curve and a year of counts logged.
 */
final readonly class PlannerBench
{
    private Planner $planner;

    private Goal $month;

    private Goal $decade;

    private Progress $year;

    public function __construct()
    {
        $start = Day::fromString('2026-01-01');
        $this->planner = new Planner();
        $this->month = new Goal('month', ResourceReference::of('member', '1'), 50_000, $start, $start->plus(29));
        $this->decade = new Goal('decade', ResourceReference::of('member', '1'), 1_000_000_000, $start, $start->plus(Goal::MAX_DAYS - 1), curve: Curve::frontLoaded(), writingDays: WritingDays::on(1, 2, 3, 4, 5));
        $year = Progress::none();

        for ($offset = 0; $offset < 365; ++$offset) {
            $year = $year->with($start->plus($offset), 1_000 + $offset);
        }

        $this->year = $year;
    }

    /**
     * @Revs(200)
     */
    public function benchAMonth(): void
    {
        $this->planner->plan($this->month, Progress::none(), Day::fromString('2026-01-15'));
    }

    /**
     * @Revs(20)
     */
    public function benchTenYears(): void
    {
        $this->planner->plan($this->decade, $this->year, Day::fromString('2027-01-01'));
    }
}
