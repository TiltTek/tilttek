<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * Values taken as dice already rolled: `[10,25,43,8]kh1` keeps the highest. A list takes the modifiers that apply to
 * dice once rolled, none that rolls more.
 */
final readonly class ValueList implements Node
{
    /**
     * @var non-empty-list<int>
     */
    public array $values;

    /**
     * @param list<int> $values
     */
    public function __construct(
        array $values,
        public Modifiers $modifiers = new Modifiers(),
    ) {
        if ([] === $values) {
            throw InvalidDefinition::because('A list of values holds at least one value.');
        }

        if ($modifiers->rolls()) {
            throw InvalidDefinition::because('A list of values is not rolled: it takes no reroll and no explosion.');
        }

        $this->values = $values;
        $modifiers->check(\count($this->values), min($this->values), max($this->values));
    }
}
