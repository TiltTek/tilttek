<?php

declare(strict_types=1);

namespace Tilttek\Party\Contract;

use Tilttek\Party\PartyChanged;

/**
 * Told once of each change of a party, after the store wrote it: the application relays the event — a notification, a
 * message on its bus — if it wants to. An implementation keeps no state between two calls.
 */
interface PartyObserver
{
    public function changed(PartyChanged $event): void;
}
