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
            'deck-format' => 'tests/Fuzz/deck-format.php',
            'deck' => 'tests/Fuzz/deck.php',
        ],
    ],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // A numeric string and its integer are the same array key: the key is the same either way.
            'CastString' => ['ignoreSourceCodeByRegex' => ['.*\\$counts\\[\\(string\\) \\$name\\] = \\$count;']],
            // `isset()` is true for a key holding false as well: the flag is read by its key alone.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\\$seen\\[\\$card\\] = true;', '.*\\$codes\\[\\$list->code\\] = true;']],
            // Without `D`, `$` also matches before a final line feed, which `trim()` refuses on the next test.
            'PregMatchRemoveFlags' => ['ignoreSourceCodeByRegex' => ['.*preg_match\\(\'/\\^\\[\\^\\\\p\\{Cc\\}\\]\\+\\$/Du\'.*']],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
