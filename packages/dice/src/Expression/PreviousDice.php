<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The dice that counted in the set rolled or listed just before, in the same expression, judged again without being
 * rolled again: `8d10>=6-@=1` takes the ones away from the successes. It takes the modifiers that apply to dice once
 * rolled; a selection keeps or drops what it can of the dice it finds.
 */
final readonly class PreviousDice implements Node
{
    public function __construct(
        public Modifiers $modifiers = new Modifiers(),
    ) {
        if ($modifiers->rolls()) {
            throw InvalidDefinition::because('The dice of the set before are not rolled again: "@" takes no reroll and no explosion.');
        }
    }
}
