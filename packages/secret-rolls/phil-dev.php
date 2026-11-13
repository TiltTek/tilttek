<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['HMAC']],
    'fuzz' => [
        'targets' => [
            'opening' => 'tests/Fuzz/opening.php',
            'journal' => 'tests/Fuzz/journal.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // The roll and the commitment of an entry, in `SceneJournal` and `SecretRolls`: `JournalEntry::fromArray()`
            // gives a committed or revealed entry its roll and a committed entry its commitment, and the closing has
            // neither, so the casts only serve PHPStan's rules.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\(string\\) \\$entry->roll.*',
                    '.*\\(string\\) \\$entry->commitment.*',
                ],
            ],
        ],
    ],
];
