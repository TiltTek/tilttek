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
            'reference-tables-document' => 'tests/Fuzz/document.php',
            'reference-tables-range' => 'tests/Fuzz/range.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // `ReferenceTable::position()`: a middle of `$low + $high` instead of its half always starts at the top of the
            // ranges and walks down to the row or past it, so the search still ends on the same position, only in
            // linear instead of logarithmic time, which no test of the result can tell.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$middle = \\(\\$low \\+ \\$high\\) >> 1;.*'],
            ],
        ],
    ],
];
