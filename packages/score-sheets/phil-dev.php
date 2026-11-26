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
            'sheet' => 'tests/Fuzz/sheet.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The sets of identifiers are read with `isset()`, which is true for a key whose value is `false` and false
            // only for `null`: the value stored under a key never matters.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$(known|teamIds|placed)\\[.*\\] = true;'],
            ],
            // A rank is set from the first key of the loop, whatever the value it starts from: the loop always runs
            // its `0 === $index` or first-value branch before the variable is read.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$(place|rank) = 0;'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
