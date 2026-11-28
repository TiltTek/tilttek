<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Rules;

use Phil\Resource\ResourceReference;
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\Contract\CarrierCapacity;
use Tilttek\Inventory\Inventory;

/**
 * Every carrier has the rules' default capacity.
 */
final readonly class DefaultCapacity implements CarrierCapacity
{
    public function capacity(Inventory $inventory, ResourceReference $carrier, CarryRules $rules): int
    {
        return $rules->defaultCapacity;
    }
}
