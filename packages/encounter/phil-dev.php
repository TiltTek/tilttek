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
            'declaration' => 'tests/Fuzz/declaration.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // `isset()` is true for `false` as for `true`: the value only marks the identifier as seen.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$keys\\[\\$band->key\\] = true;.*',
                    '.*\\$seen\\[\\$participant->id\\] = true;.*',
                    '.*\\$combatants\\[\\$combatant\\] = true;.*',
                ],
            ],
            // The floor of the average level is a whole number kept as a float, which reads the same as an array key,
            // in a comparison and in `%d`; and a text already checked as valid UTF-8 never makes `preg_match_all()`
            // return `false`.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$party = \\(int\\) floor\\(.*',
                    '.*\\(int\\) preg_match_all\\(.*',
                ],
            ],
            // `map()` already refuses a map where a list is asked: `array_values()` only gives PHPStan the `list` of the
            // result.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_map\\(static fn \\(mixed \\$entry\\): string => .*array_values\\(.*',
                    // The identifier of a game system starts with a letter, so the rules by system have string keys only:
                    // the spread makes named arguments the variadic collects, and the constructor indexes them afresh.
                    '.*new self\\(\\.\\.\\.array_values\\(\\[\\.\\.\\.\\$bySystem, \\.\\.\\.\\$given\\]\\)\\);',
                ],
            ],
            // `EncounterPlanner::place()` runs once both sides are known not to be given together: `before` and `after`
            // are never both set, so the order of the coalescing changes nothing.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => ['.*\\$anchor = \\$before \\?\\? \\$after;.*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
