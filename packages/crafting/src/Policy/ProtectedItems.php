<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Policy;

use Tilttek\Crafting\Contract\ConsumptionPolicy;
use Tilttek\Crafting\Text;

/**
 * A fixed list of items no craft consumes — the items sold for money, for instance. They may still be tools, held
 * and never taken.
 */
final readonly class ProtectedItems implements ConsumptionPolicy
{
    /** @var array<string, true> */
    private array $items;

    /**
     * @param list<string> $items
     */
    public function __construct(array $items)
    {
        $protected = [];

        foreach ($items as $item) {
            $protected[Text::code($item, 'a protected item')] = true;
        }

        $this->items = $protected;
    }

    public function mayConsume(string $item): bool
    {
        return !isset($this->items[$item]);
    }
}
