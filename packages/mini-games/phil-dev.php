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
            'code' => 'tests/Fuzz/code.php',
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
        ],
    ],
];
