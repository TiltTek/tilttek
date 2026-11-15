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
            'definition' => 'tests/Fuzz/definition.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // A delivery fails only through the dispatcher: without one, no holder is ever postponed, so the null-safe
            // call that reports a failure never meets a null dispatcher.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$this->events\\?->dispatch\\(new DeliveryKeepsFailing\\(.*',
                ],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
