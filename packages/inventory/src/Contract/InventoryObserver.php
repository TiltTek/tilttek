<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Contract;

use Tilttek\Inventory\InventoryChanged;

/**
 * Told once of each change of an inventory, after the store wrote it: the application relays the event — a
 * notification, a message on its bus — if it wants to. An implementation keeps no state between two calls.
 */
interface InventoryObserver
{
    public function changed(InventoryChanged $event): void;
}
