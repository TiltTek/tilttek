<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Contract;

use Tilttek\TableRules\Event\RulesEvent;

/**
 * Told of each fact once the rulebook is kept, by identifiers only.
 */
interface TableRulesObserver
{
    public function notify(RulesEvent $event): void;
}
