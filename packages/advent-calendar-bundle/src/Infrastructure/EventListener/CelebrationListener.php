<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\EventListener;

use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendarBundle\Application\Celebrations;

/**
 * The hook of the celebration: each first opening `BoxOpened` tells is kept for the screen that answers the request,
 * which plays the celebration of the kit when it is installed and enabled. The application's own listeners of
 * `BoxOpened` — the one that grants the surprise first — run beside it.
 */
final readonly class CelebrationListener
{
    public function __construct(
        private Celebrations $celebrations,
    ) {
    }

    public function __invoke(BoxOpened $event): void
    {
        $this->celebrations->record($event);
    }
}
