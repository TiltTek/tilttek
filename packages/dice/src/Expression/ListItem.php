<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * An item of a text list: a text or a translation key, never HTML, and how often it is drawn — by a weight, or by the
 * range of numbers it covers on the list's die.
 */
final readonly class ListItem
{
    public function __construct(
        public string $text,
        public int $weight = 1,
        public ?Range $range = null,
    ) {
        if ('' === trim($text)) {
            throw InvalidDefinition::because('An item of a list has a text.');
        }

        if ($weight < 1) {
            throw InvalidDefinition::because(\sprintf('An item of a list weighs at least 1; %d does not.', $weight));
        }

        if ($range instanceof Range && 1 !== $weight) {
            throw InvalidDefinition::because('An item covers a range or weighs, not both.');
        }
    }

    /**
     * How many numbers of the list's die draw the item: its weight, or the width of its range.
     */
    public function chances(): int
    {
        return $this->range instanceof Range ? $this->range->to - $this->range->from + 1 : $this->weight;
    }
}
