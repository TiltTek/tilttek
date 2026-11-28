<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Contract;

use Tilttek\GameNight\Event\NightEvent;

/**
 * Told of the facts of a night once they are kept, each once: the library never repeats one, and sends no reminder.
 */
interface NightObserver
{
    public function notify(NightEvent $event): void;
}
