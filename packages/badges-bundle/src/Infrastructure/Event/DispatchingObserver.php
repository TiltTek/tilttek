<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Event;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Badges\Award;
use Tilttek\Badges\AwardObserver;
use Tilttek\Badges\Badge;

/**
 * Turns what the projection tells into events of the application's event dispatcher.
 */
final readonly class DispatchingObserver implements AwardObserver
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    #[Override]
    public function awarded(Award $award, Badge $badge): void
    {
        $this->dispatcher->dispatch(new BadgeAwarded($award, $badge));
    }

    #[Override]
    public function revoked(Award $award, Badge $badge): void
    {
        $this->dispatcher->dispatch(new BadgeRevoked($award, $badge));
    }
}
