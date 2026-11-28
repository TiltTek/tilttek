<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Contract;

use Tilttek\GameShelf\Event\ShelfEvent;

/**
 * Told of the facts of a shelf once it is kept, each once.
 */
interface ShelfObserver
{
    public function notify(ShelfEvent $event): void;
}
