<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * Items drawn from a list of texts: `1L[sword,bow]` draws one; `3Lu[a,b,c,d]` three different ones; `3Ln[ka,ro,mi]`
 * three joined without a separator, into one text. Items may weigh more than one — `1L[a[2],b]` — or cover the ranges
 * of a die — `1L[1..2:a,3..6:b]`, which rolls from the lowest bound to the highest, the ranges following each other.
 * The count is a number or an expression rolled first.
 */
final readonly class TextList implements Node
{
    /**
     * @var non-empty-list<ListItem>
     */
    public array $items;

    /**
     * @param list<ListItem> $items
     */
    public function __construct(
        public Node $count,
        array $items,
        public bool $unique = false,
        public bool $joined = false,
    ) {
        if ([] === $items) {
            throw InvalidDefinition::because('A list holds at least one item.');
        }

        $this->items = $items;
        $ranges = [];

        foreach ($items as $item) {
            if ($item->range instanceof Range) {
                $ranges[] = $item->range;
            }
        }

        if ([] !== $ranges) {
            $this->checkRanges($ranges);
        }

        if ($count instanceof Constant) {
            $this->checkCount($count->value);
        }
    }

    /**
     * @throws InvalidDefinition when the list cannot draw this many items
     */
    public function checkCount(int $count): void
    {
        if ($count < 1) {
            throw InvalidDefinition::because(\sprintf('A list draws at least one item; %d is not.', $count));
        }

        if ($this->unique && $count > \count($this->items)) {
            throw InvalidDefinition::because(\sprintf('%d different items cannot be drawn from %d.', $count, \count($this->items)));
        }
    }

    /**
     * @param non-empty-list<Range> $ranges
     */
    private function checkRanges(array $ranges): void
    {
        if (\count($ranges) !== \count($this->items)) {
            throw InvalidDefinition::because('The items of a list all cover a range, or none does.');
        }

        foreach (\array_slice($ranges, 1) as $index => $range) {
            if ($range->from !== $ranges[$index]->to + 1) {
                throw InvalidDefinition::because(\sprintf('The ranges of a list follow each other: %d..%d comes after %d.', $range->from, $range->to, $ranges[$index]->to));
            }
        }
    }
}
