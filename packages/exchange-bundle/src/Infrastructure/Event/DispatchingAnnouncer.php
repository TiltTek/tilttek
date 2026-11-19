<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Event;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Exchange\Announcer;
use Tilttek\Exchange\Event\ExchangeEvent;

/**
 * Dispatches the exchanges' events to the application's event dispatcher: a listener of `RewardReserved` delivers the
 * reward, a listener of `OfferMade` tells the member it was made to.
 */
final readonly class DispatchingAnnouncer implements Announcer
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    #[Override]
    public function announce(ExchangeEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
