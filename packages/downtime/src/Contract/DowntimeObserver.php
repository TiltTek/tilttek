<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Contract;

use Tilttek\Downtime\Event\DowntimeEvent;

/**
 * Told of each fact once the entries are kept, by identifiers only.
 */
interface DowntimeObserver
{
    public function notify(DowntimeEvent $event): void;
}
