<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Contract;

use Tilttek\PlayLog\Event\PlayEvent;

/**
 * Told of each fact once the play is kept, by identifiers only.
 */
interface PlayObserver
{
    public function notify(PlayEvent $event): void;
}
