<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Contract;

use Tilttek\InitiativeCards\Event\InitiativeEvent;

/**
 * Told of each fact once the encounter is kept, by identifiers only.
 */
interface InitiativeObserver
{
    public function notify(InitiativeEvent $event): void;
}
