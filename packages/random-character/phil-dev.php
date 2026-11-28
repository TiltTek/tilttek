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
            'random-character-document' => 'tests/Fuzz/document.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // `CharacterRoller::value()`: a generator gives texts, structured results and maps, never another type; the
            // test only narrows `mixed` for PHPStan, so the `array_map` that applies it and a negated test change nothing.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => ['.*return array_map\\(\\$this->value\\(\\.\\.\\.\\), \\$result->results\\);'],
            ],
            'LogicalOrAllSubExprNegation' => [
                'ignoreSourceCodeByRegex' => ['.*return \\\\is_string\\(\\$result\\) \\|\\| \\\\is_array\\(\\$result\\) \\? \\$result : null;'],
            ],
            // `preg_replace()` gives `null` only on an error of the engine, which the fixed placeholder pattern cannot
            // raise; the casts only give PHPStan the `string`.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$name = \\(string\\) preg_replace_callback\\(.*',
                    '.*\\$plain = \\(string\\) preg_replace\\(.*',
                ],
            ],
        ],
    ],
];
