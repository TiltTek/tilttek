<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Application;

use Override;
use Symfony\Contracts\Service\ResetInterface;
use Tilttek\AdventCalendar\Event\BoxOpened;

/**
 * The first openings of the current request, as `BoxOpened` told them, for the screen that answers it to celebrate: a
 * box opened again, which dispatches nothing, is never celebrated. Emptied after each request, so that a worker never
 * celebrates for one member what another opened.
 */
final class Celebrations implements ResetInterface
{
    /** @var array<string, true> */
    private array $opened = [];

    public function record(BoxOpened $event): void
    {
        $this->opened[$event->idempotencyKey()] = true;
    }

    /**
     * Whether the request opened this box for the first time; the answer is given once.
     */
    public function take(string $calendar, string $member, int $day): bool
    {
        $key = \sprintf('advent:%s:%s:%d', $calendar, $member, $day);
        $opened = isset($this->opened[$key]);
        unset($this->opened[$key]);

        return $opened;
    }

    #[Override]
    public function reset(): void
    {
        $this->opened = [];
    }
}
