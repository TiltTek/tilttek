<?php

declare(strict_types=1);

namespace Tilttek\Celebrations\Contract;

use Tilttek\Celebrations\CelebrationChanged;

/**
 * Told once of each change of an inbox, after the store wrote it: the application relays the event — a count pushed to
 * the member's open pages, a message on its bus — if it wants to. An implementation keeps no state between two calls.
 */
interface CelebrationObserver
{
    public function changed(CelebrationChanged $event): void;
}
