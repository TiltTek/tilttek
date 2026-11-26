<?php

declare(strict_types=1);

namespace Tilttek\Handouts\Contract;

use Tilttek\Handouts\HandoutChanged;

/**
 * Told once of each change of a handout, after the store wrote it: the application relays the event — a push to the
 * players' screens, a message on its bus — if it wants to. An implementation keeps no state between two calls.
 */
interface HandoutObserver
{
    public function changed(HandoutChanged $event): void;
}
