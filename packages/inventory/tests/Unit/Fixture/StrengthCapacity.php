<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Tests\Unit\Fixture;

use Phil\Resource\ResourceReference;
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\Contract\CarrierCapacity;
use Tilttek\Inventory\Inventory;

/**
 * A capacity read from a table of carriers, the rules' default for the others.
 */
final readonly class StrengthCapacity implements CarrierCapacity
{
    /**
     * @param array<string, int> $capacities by carrier reference
     */
    public function __construct(
        private array $capacities,
    ) {
    }

    public function capacity(Inventory $inventory, ResourceReference $carrier, CarryRules $rules): int
    {
        return $this->capacities[$carrier->toString()] ?? $rules->defaultCapacity;
    }
}
