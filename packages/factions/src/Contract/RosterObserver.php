<?php

declare(strict_types=1);

namespace Tilttek\Factions\Contract;

use Tilttek\Factions\Event\RosterEvent;

/**
 * Told of each fact once the roster is kept, by identifiers only.
 */
interface RosterObserver
{
    public function notify(RosterEvent $event): void;
}
