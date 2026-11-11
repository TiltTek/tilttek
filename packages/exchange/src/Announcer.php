<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

use Tilttek\Exchange\Event\ExchangeEvent;

/**
 * Told what happened to an exchange — a reservation made, confirmed, cancelled or expired, points transferred, an
 * offer made, accepted, declined, withdrawn, countered or expired —, once, when it happens: the application delivers
 * a reward or tells a member from there.
 */
interface Announcer
{
    public function announce(ExchangeEvent $event): void;
}
