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
            'log' => 'tests/Fuzz/log.php',
            'state' => 'tests/Fuzz/state.php',
            'snapshot' => 'tests/Fuzz/snapshot.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The phase transitions listed by `Phases::from()`: the validator of the state machine refuses two
            // transitions of the same name from one phase, so the list has no duplicate to remove and no gap to close.
            'UnwrapArrayUnique' => [
                'ignoreSourceCodeByRegex' => ['.*array_values\\(array_unique\\(array_map\\(.*getEnabledTransitions.*'],
            ],
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*array_values\\(array_unique\\(array_map\\(.*getEnabledTransitions.*'],
            ],
            // A new seed, in `Randomness::seed()`: the digest has 64 hexadecimal digits whatever the length of what it
            // hashes, so only the entropy of the random bytes changes, which no test can observe.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*hash\\(\'sha256\', random_bytes\\(32\\)\\);'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*hash\\(\'sha256\', random_bytes\\(32\\)\\);'],
            ],
            // The seat of a move, in `RoleplayingScene`: PHP turns a null or a numeric text key into the key the cast
            // gives, and a seat identifier starts with a letter, so the cast only serves PHPStan's rules.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$seat = \\(string\\) \\$move->seat;',
                    '.*\\$checked\\[\\(string\\) \\$seat\\] = .*',
                    // The keys of a mapping, in `Fields::map()`: the foreach already gives an integer or a text, and PHP turns a
                    // numeric text key into the integer key, so the cast only serves the types of PHPStan's rules.
                    '.*\\$map\\[\\(string\\) \\$key\\] = \\$item;',
                ],
            ],
            // The values of the options, in `TableOptions::resolve()`: the resolver has checked the type of every value,
            // so the default arm of the match never runs; it only narrows the type for PHPStan.
            'MatchArmRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*default => \\$option->default,'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
