<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // The answer is checked as UTF-8 before it is normalized, and preg_replace() answers null only on a subject
            // that is not: the cast never meets a null, and the mutant without it cannot be told apart.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['\\$text = \\(string\\) preg_replace\\(.*'],
            ],
        ],
    ],
    'fuzz' => [
        'targets' => [
            'scenario' => 'tests/Fuzz/scenario.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
