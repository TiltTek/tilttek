<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'fuzz' => [
        'targets' => [
            'rules' => 'tests/Fuzz/rules.php',
        ],
    ],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // The holders are only read through a loop: the list is for the contract, and its keys are never seen.
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['.*return array_values\\(\\$holders\\);']],
            // `isset()` is true for a key holding false as well: the session is read by its key alone.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\\$this->sessions\\[\\$key\\]\\[\\$entry->session \\?\\? \'\'\\] = true;']],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
