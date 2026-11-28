<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Contract;

use Tilttek\Crafting\Crafted;

/**
 * What the application does once a craft was applied: an achievement, a line of the activity, a message on a bus. A
 * craft found again under its key is not told twice.
 */
interface CraftObserver
{
    public function crafted(Crafted $event): void;
}
