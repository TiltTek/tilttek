<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Die\CustomDie;
use Tilttek\Dice\Die\DieType;
use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * Dice whose count, or whose number of sides, is rolled first: `(1d4+1)d6`, `3d(2*6)`. The dice are checked against
 * their modifiers once the count and the sides are known; a custom die takes none.
 */
final readonly class ComputedDice implements Node
{
    /**
     * @param Node|DieType $die the die, or the expression that gives its number of sides
     */
    public function __construct(
        public Node $count,
        public Node|DieType $die,
        public Modifiers $modifiers = new Modifiers(),
    ) {
        if ($die instanceof CustomDie && !$modifiers->isEmpty()) {
            throw InvalidDefinition::because('A custom die takes no modifier.');
        }
    }
}
