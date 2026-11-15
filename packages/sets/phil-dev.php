<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The library is plain PHP on phil/resource and the PSR interfaces, and never sees the optional Symfony
        // integration in its namespace; composer-dependency-analyser.php keeps the console, Messenger, the Scheduler
        // and the locks, required in development only, to that integration.
        'layers' => [
            'TilttekSets' => '^Tilttek\\\\Sets\\\\(?!Symfony\\\\).*',
            'TilttekSetsSymfony' => '^Tilttek\\\\Sets\\\\Symfony\\\\.*',
        ],
        'ruleset' => [
            'TilttekSets' => ['DefaultAllowed', 'PhilResource'],
            'TilttekSetsSymfony' => ['TilttekSets', 'DefaultAllowed', 'PhilResource'],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'fuzz' => [
        'targets' => [
            'definition' => 'tests/Fuzz/definition.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
