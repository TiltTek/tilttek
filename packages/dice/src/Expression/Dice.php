<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Die\DieType;
use Tilttek\Dice\Die\NumericDie;
use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A number of dice of one kind and their modifiers ([Modifiers] says the order they apply in).
 *
 * Only numeric dice take modifiers. The modifiers are checked against the dice: a selection keeps from one to all of
 * them, or drops from one to all but one; an explosion or a repeated reroll that every face would trigger is refused,
 * since it would never end; repeated unique dice need as many faces as dice; dice all the same explode from two dice.
 */
final readonly class Dice implements Node
{
    public function __construct(
        public int $count,
        public DieType $die,
        public Modifiers $modifiers = new Modifiers(),
    ) {
        if ($count < 1) {
            throw InvalidDefinition::because('An expression rolls at least one die of each kind it names.');
        }

        if ($die instanceof NumericDie) {
            $modifiers->check($count, $die->lowest(), $die->highest());
        } elseif (!$modifiers->isEmpty()) {
            throw InvalidDefinition::because('A custom die takes no modifier.');
        }
    }
}
