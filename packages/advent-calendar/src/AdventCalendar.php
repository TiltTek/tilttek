<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendar\Exception\BoxNotOpenable;

/**
 * Opens the boxes of a calendar for a member and shows them as that member sees them. Opening is idempotent: the
 * first opening is recorded and dispatched as `BoxOpened`; opening the same box again answers the recorded opening.
 *
 * An opening is marked delivered once its `BoxOpened` was dispatched without an exception. When a listener throws, the
 * opening stays undelivered, the member's other undelivered openings of the calendar are told all the same, and the
 * first exception is thrown again at the end; the next `open()` of the calendar by the member, or `deliver()`, tells it
 * again. An opening may thus be told more than once — two requests at the same moment, a listener that failed after
 * granting —: the listeners are idempotent, by `BoxOpened::idempotencyKey()`. The service keeps no state between two
 * calls.
 */
final readonly class AdventCalendar
{
    public function __construct(
        private OpeningStore $openings,
        private ClockInterface $clock,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws BoxNotOpenable when the box was never opened and its window is not open now
     */
    public function open(Calendar $calendar, ResourceReference $member, int $day): OpenedBox
    {
        $surprise = $calendar->surprise($day);
        $previous = $this->openings->find($calendar->id, $member, $day);

        if ($previous instanceof Opening) {
            $this->tell($calendar, $member);

            return new OpenedBox($previous, $surprise, false);
        }

        $now = $this->clock->now();
        $window = $calendar->window($day);

        if (!$window->hasStarted($now)) {
            throw BoxNotOpenable::notYet($calendar->id, $day, $window->from);
        }

        if ($window->hasEnded($now)) {
            throw BoxNotOpenable::missed($calendar->id, $day, $window->until);
        }

        $opening = new Opening($calendar->id, $member, $day, $now);

        $first = $this->openings->add($opening);
        $kept = $first ? $opening : $this->openings->find($calendar->id, $member, $day) ?? $opening;
        $this->tell($calendar, $member);

        return new OpenedBox($kept, $surprise, $first);
    }

    /**
     * Tells every opening of the member in the calendar not yet delivered, as `open()` does; returns how many were
     * settled. An application calls it to resume after a listener failed. An opening of a day the calendar no longer
     * holds is let go without the listeners.
     */
    public function deliver(Calendar $calendar, ResourceReference $member): int
    {
        return $this->tell($calendar, $member);
    }

    /**
     * Every box of the calendar as the member sees it now, by day.
     *
     * @return list<BoxView>
     */
    public function boxes(Calendar $calendar, ResourceReference $member): array
    {
        $now = $this->clock->now();
        $opened = [];

        foreach ($this->openings->openingsOf($calendar->id, $member) as $opening) {
            $opened[$opening->day] = $opening;
        }

        $boxes = [];

        for ($day = 1; $day <= $calendar->days(); ++$day) {
            $boxes[] = $this->view($calendar, $day, $opened[$day] ?? null, $now);
        }

        return $boxes;
    }

    /**
     * One box as the member sees it now.
     */
    public function box(Calendar $calendar, ResourceReference $member, int $day): BoxView
    {
        return $this->view($calendar, $day, $this->openings->find($calendar->id, $member, $day), $this->clock->now());
    }

    /**
     * Removes every opening of the member, in every calendar.
     *
     * @return int how many openings were removed
     */
    public function erase(ResourceReference $member): int
    {
        return $this->openings->eraseMember($member);
    }

    /**
     * Dispatches the `BoxOpened` of each undelivered opening of the member in the calendar and marks it delivered; an
     * opening whose listener throws stays undelivered, and the first exception is thrown again once the others are told.
     */
    private function tell(Calendar $calendar, ResourceReference $member): int
    {
        $failure = null;
        $settled = 0;

        foreach ($this->openings->undelivered($member) as $opening) {
            if ($opening->calendar !== $calendar->id) {
                continue;
            }

            if ($opening->day <= $calendar->days()) {
                try {
                    $this->dispatcher->dispatch(new BoxOpened($opening->calendar, $opening->member, $opening->day, $calendar->surprise($opening->day), $opening->openedAt));
                } catch (Throwable $thrown) {
                    $failure ??= $thrown;

                    continue;
                }
            }

            $this->openings->markDelivered($opening);
            ++$settled;
        }

        if ($failure instanceof Throwable) {
            throw $failure;
        }

        return $settled;
    }

    private function view(Calendar $calendar, int $day, ?Opening $opening, DateTimeImmutable $now): BoxView
    {
        $window = $calendar->window($day);
        $date = $calendar->dateOf($day);

        if ($opening instanceof Opening) {
            return new BoxView($day, $date, BoxState::Opened, $window, $calendar->surprise($day), $opening->openedAt);
        }

        $state = match (true) {
            !$window->hasStarted($now) => BoxState::Locked,
            $window->hasEnded($now) => BoxState::Missed,
            default => BoxState::Openable,
        };

        return new BoxView($day, $date, $state, $window);
    }
}
