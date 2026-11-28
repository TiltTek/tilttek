<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'fuzz' => [
        // The collection reader on files nobody wrote, from the seeds of tests/Fuzz/corpus/<target>: whatever it reads,
        // it returns rows within the limits, or refuses the file with an exception of the library.
        'targets' => [
            'collection-file' => 'tests/Fuzz/collection-file.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['BoardGameGeek', 'RPGGeek', 'OpenTabletop', 'Wikidata', 'GTIN', 'sourcebooks', 'larp']],
    'infection' => [
        'mutators' => [
            // The check digit sums the digits of a string of digits: PHP multiplies a numeric string as the integer it
            // spells, so the cast to `int` only states it.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$sum \\+= \\(int\\) \\$digits\\[\\$i\\].*',
                    // The column of the identifier is required, so `find()` never returns `null` for it: the cast only
                    // gives PHPStan the `int` of the shape, and a position is the same number with or without it.
                    '.*.identifier. => \\(int\\) \\$find\\(.*',
                ],
            ],
            // A file with the database given for all rows has no column of it: the cell read is empty, `tryFrom('')` is
            // `null`, and either side of the coalesce gives the one database; both are never set, `Columns` refuses it.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => ['.*\\$columns->scheme \\?\\? IdentifierScheme::tryFrom.*'],
            ],
        ],
    ],
];
