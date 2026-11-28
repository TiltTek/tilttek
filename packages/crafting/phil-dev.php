<?php

declare(strict_types=1);

use Tilttek\Crafting\Crafter;
use Tilttek\Crafting\Holding;
use Tilttek\Crafting\Ingredient;
use Tilttek\Crafting\Policy\ProtectedItems;
use Tilttek\Crafting\Pool;
use Tilttek\Crafting\Recipe;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['craftable', 'Craftable', 'larp', 'larps']],
    'fuzz' => [
        'targets' => [
            'recipe-book' => 'tests/Fuzz/recipe-book.php',
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // `Crafter::mayBeCraftable()` only spares `craftable()` the plans that cannot succeed: answering true
            // instead of false, true for every recipe, or a held quantity of -1 or 1 for an item not held builds the
            // plan, which finds the same missing items.
            'FalseValue' => ['ignore' => [Crafter::class.'::mayBeCraftable']],
            'ArrayAll' => ['ignore' => [Crafter::class.'::mayBeCraftable']],
            // `Crafter::plan()` reads an item a selection chooses and the pool does not hold as 0 held: with -1 held it
            // takes nothing either, since only a positive quantity is taken.
            'DecrementInteger' => ['ignore' => [Crafter::class.'::mayBeCraftable', Crafter::class.'::plan']],
            'IncrementInteger' => ['ignore' => [Crafter::class.'::mayBeCraftable']],
            // Sets of codes kept as `array<string, true>` and read with `isset()` or `array_keys()`: a false value is
            // as present as a true one, and only the keys count.
            'TrueValue' => ['ignore' => [
                Holding::class.'::__construct',
                ProtectedItems::class.'::__construct',
                Pool::class.'::of',
                Recipe::class.'::checkIngredients',
                Recipe::class.'::checkItems',
            ]],
            // `Ingredient::toArray()` casts the tag of an ingredient asked by a tag, which is a string there, for the
            // array shape alone: the value is the same without the cast.
            'CastString' => ['ignore' => [Ingredient::class.'::toArray']],
        ],
    ],
];
