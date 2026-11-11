<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Functional\App;

use Tilttek\BadgesBundle\Infrastructure\Event\BadgeAwarded;
use Tilttek\BadgesBundle\Infrastructure\Event\BadgeRevoked;

/**
 * Writes down the events of the badges.
 */
final class Recorder
{
    /**
     * @var list<string>
     */
    public array $events = [];

    public function awarded(BadgeAwarded $event): void
    {
        $this->events[] = 'awarded '.$event->badge->code.' '.$event->award->member->toString();
    }

    public function revoked(BadgeRevoked $event): void
    {
        $this->events[] = 'revoked '.$event->badge->code.' '.$event->award->member->toString();
    }
}
