<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'fuzz' => [
        'targets' => [
            'crazy-eights-move' => 'tests/Fuzz/crazy-eights-move.php',
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // The names of the games are lowercase words, never whole numbers, so the engine's `Games` takes them as the
            // names of the variadic arguments they spread into: the list and the array by name give the same `Games`.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*new Games\\(\\.\\.\\.array_values\\(\\$this->games\\)\\).*'],
            ],
            // A seat is a name that starts with a letter, never a whole number: PHP turns the key of a numeric string
            // into the integer it spells, so the cast only gives PHPStan the `string` key of the shape.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$banked\\[\\(string\\) \\$seat\\] = .*',
                    '.*\\$loaded\\[\\(string\\) \\$seat\\] = .*',
                ],
            ],
            // The cards of the hand of the seat that looks are all seen by it: their identifier and face are set, and
            // the `?->` only states the nullable type of what the others see.
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => ['.*array_map\\(static fn \\(SeenCard \\$card\\): array => \\[.card. => \\$card->id\\?->value.*'],
            ],
            // The first die of two dice is the first die of one: the dice fall in order from one randomizer, and
            // nothing else is drawn after the turn's roll.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$face = \\$this->dice->roll\\(1, \\$random\\)\\[0\\];.*'],
            ],
        ],
    ],
];
