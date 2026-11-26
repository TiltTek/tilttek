<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Contract;

use Tilttek\SessionPrep\Event\PreparationEvent;

/**
 * Told of each fact once the preparation is kept, by identifiers only.
 */
interface PreparationObserver
{
    public function notify(PreparationEvent $event): void;
}
