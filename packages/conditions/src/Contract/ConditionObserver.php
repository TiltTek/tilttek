<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Contract;

use Tilttek\Conditions\Event\ConditionEvent;

/**
 * Told of each fact once the conditions are kept, by identifiers only.
 */
interface ConditionObserver
{
    public function notify(ConditionEvent $event): void;
}
