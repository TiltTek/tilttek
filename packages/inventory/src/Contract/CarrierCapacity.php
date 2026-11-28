<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\Inventory;

/**
 * The capacity of a carrier, by the measure the rules count: the application reads it from the character's sheet — 15
 * times its strength, 10 slots plus its constitution — or gives the rules' default. An implementation keeps no state
 * between two requests beside what it reads.
 */
interface CarrierCapacity
{
    /**
     * @return int 0 to 1,000,000,000,000
     */
    public function capacity(Inventory $inventory, ResourceReference $carrier, CarryRules $rules): int;
}
