<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

use Override;
use Tilttek\Exchange\Event\ExchangeEvent;

/**
 * Tells no one.
 */
final readonly class SilentAnnouncer implements Announcer
{
    #[Override]
    public function announce(ExchangeEvent $event): void
    {
    }
}
