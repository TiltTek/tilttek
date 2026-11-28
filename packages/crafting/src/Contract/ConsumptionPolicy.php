<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Contract;

/**
 * Which items a recipe may consume. An item a member paid for, or one the site keeps out of crafting, is never taken
 * by a craft: the recipe book refuses a recipe that names it, and a tag never picks it.
 */
interface ConsumptionPolicy
{
    public function mayConsume(string $item): bool;
}
