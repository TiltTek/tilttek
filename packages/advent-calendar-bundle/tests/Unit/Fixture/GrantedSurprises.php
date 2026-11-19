<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Fixture;

use Tilttek\AdventCalendar\Event\BoxOpened;

/**
 * The application's listener of the test: it records the surprises it would grant, by their idempotency key.
 */
final class GrantedSurprises
{
    /** @var list<string> */
    public array $granted = [];

    public function __invoke(BoxOpened $event): void
    {
        $this->granted[] = $event->idempotencyKey();
    }
}
