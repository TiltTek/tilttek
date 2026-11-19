<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'words' => ['drand', 'quicknet']],
    'fuzz' => [
        'targets' => [
            'proof' => 'tests/Fuzz/proof.php',
            'drand' => 'tests/Fuzz/drand.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // The public key of each chain read, in `DrandBeacon::chain()`: `DrandResponse::info()` refuses an answer
            // without its key, so the cast only serves PHPStan's rules.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\(string\\) DrandResponse::info\\(.*'],
            ],
            // The moment of the clock, in `DrandBeacon::bodies()`: `format('U.u')` gives a numeric string, which PHP adds
            // to a float and compares with one as a number, so the cast only serves PHPStan's rules.
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->clock->now\\(\\)->format\\(.U\\.u.\\).*'],
            ],
            // The answers and the relays of `DrandBeacon`: they are only counted, compared and read with `reset()`, so
            // their keys never show.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return array_values\\(\\$bodies\\);',
                    '.*\\$checked = array_values\\(array_unique\\(\\$checked\\)\\);',
                ],
            ],
        ],
    ],
];
