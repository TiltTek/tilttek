<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'fuzz' => [
        'targets' => [
            'shop-code' => 'tests/Fuzz/shop-code.php',
            'draw-text' => 'tests/Fuzz/draw-text.php',
        ],
    ],
    'deptrac' => [
        // The draws are the verifiable rolls of tilttek/dice.
        'ruleset' => [
            'TilttekLootTables' => ['DefaultAllowed', 'TilttekDice'],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'docs' => ['public' => true],
];
