<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Tests\Benchmark;

use Phil\Resource\ResourceReference;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\MockClock;
use Tilttek\AdventCalendar\AdventCalendar;
use Tilttek\AdventCalendar\Calendar;
use Tilttek\AdventCalendar\Store\InMemoryOpeningStore;
use Tilttek\AdventCalendar\Surprise;

/**
 * The boxes a page shows at each display: a calendar of 24 boxes, and one of 92, the longest, half of them opened.
 */
final readonly class BoxesBench
{
    private AdventCalendar $advent;

    private Calendar $december;

    private Calendar $longest;

    private ResourceReference $member;

    public function __construct()
    {
        $clock = new MockClock('2026-12-01 08:00:00', 'Europe/Paris');
        $dispatcher = new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
        $this->advent = new AdventCalendar(new InMemoryOpeningStore(), $clock, $dispatcher);
        $this->member = ResourceReference::of('member', '1');
        $this->december = Calendar::of('december', 'Europe/Paris', '2026-12-01', array_fill(0, 24, Surprise::points(10)));
        $this->longest = Calendar::of('longest', 'Europe/Paris', '2026-09-01', array_fill(0, Calendar::MAX_DAYS, Surprise::points(10)));

        for ($day = 1; $day <= Calendar::MAX_DAYS; $day += 2) {
            $this->advent->open($this->longest, $this->member, $day);
        }

        $this->advent->open($this->december, $this->member, 1);
    }

    /**
     * @Revs(200)
     */
    public function benchTwentyFourBoxes(): void
    {
        $this->advent->boxes($this->december, $this->member);
    }

    /**
     * @Revs(50)
     */
    public function benchNinetyTwoBoxes(): void
    {
        $this->advent->boxes($this->longest, $this->member);
    }
}
