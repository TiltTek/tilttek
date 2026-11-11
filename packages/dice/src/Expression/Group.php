<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * Expressions rolled each with its own total, the totals taken as dice already rolled: `{4d6, 2d10}kh1` keeps the
 * higher total. A group takes the modifiers that apply to dice once rolled, none that rolls more.
 */
final readonly class Group implements Node
{
    /**
     * @var non-empty-list<Node>
     */
    public array $expressions;

    /**
     * @param list<Node> $expressions
     */
    public function __construct(
        array $expressions,
        public Modifiers $modifiers = new Modifiers(),
    ) {
        if ([] === $expressions) {
            throw InvalidDefinition::because('A group holds at least one expression.');
        }

        if ($modifiers->rolls()) {
            throw InvalidDefinition::because('The totals of a group are not rolled again: a group takes no reroll and no explosion.');
        }

        $this->expressions = $expressions;
        $modifiers->check(\count($this->expressions), \PHP_INT_MIN, \PHP_INT_MAX);
    }
}
