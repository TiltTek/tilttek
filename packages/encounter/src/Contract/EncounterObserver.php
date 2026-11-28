<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Contract;

use Tilttek\Encounter\Event\EncounterEvent;

/**
 * Told of each fact once the encounter is kept, by identifiers only.
 */
interface EncounterObserver
{
    public function notify(EncounterEvent $event): void;
}
