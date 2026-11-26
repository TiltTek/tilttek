<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'fuzz' => [
        'targets' => [
            'gesture' => 'tests/Fuzz/gesture.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The last swap of the Fisher-Yates shuffle draws among a single place, 0: `Randomizer::getInt(0, 0)` returns
            // 0 without reading the engine, so a loop that also visits place 0 swaps it with itself and gives the
            // same order from the same numbers (`>= 1` and its decrement).
            'GreaterThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['.*\\$place >= 1;.*'],
            ],
            // A new game puts every card face down, and `put()` keeps a card of the draw pile face down whatever it is
            // asked, then the shuffle turns them all down: the draw pile has no side to choose; the discard and the table
            // start empty, so the side of no card is not observable.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$state->put\\(.*ZoneId::(drawPile|discard|table)\\(\\), End::Top, false\\);'],
            ],
            // The jokers are counted from `max(0, $jokers)`, so a default of -1 asks for the same none; and the spread of
            // the filled array drops its keys, so the first key `array_fill()` gives to the jokers is never read.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$place >= 1;.*', '.*int \\$jokers = 0,.*', '.*array_fill\\(0, max\\(0, \\$jokers\\).*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*array_fill\\(0, max\\(0, \\$jokers\\).*'],
            ],
            // The swaps keep the keys 0 to n - 1, in order: the list is already a list.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*return array_values\\(\\$items\\);'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
