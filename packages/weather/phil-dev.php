<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // The exploration of a map of hexagons, which a forecast may serve.
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['hexcrawl']],
    'fuzz' => [
        'targets' => [
            'climates' => 'tests/Fuzz/climates.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // Reading more than the longest document, by one byte or by two, makes the same refusal: only a read of
            // fewer bytes than the limit lets a document through, which the tests pin.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*file_get_contents\\(\\$path, length: self::LENGTH \\+ 1\\).*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
