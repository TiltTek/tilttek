<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'docs' => ['public' => true],
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The core is plain PHP on its requirements; the reservations on phil/reservation, suggested, are an adapter
        // that alone sees it.
        'layers' => [
            'TilttekEscapeRoom' => '^Tilttek\\\\EscapeRoom\\\\(?!Reservation\\\\).*',
            'TilttekEscapeRoomReservation' => '^Tilttek\\\\EscapeRoom\\\\Reservation\\\\.*',
        ],
        'ruleset' => [
            'TilttekEscapeRoom' => ['DefaultAllowed', 'PhilResource', 'TilttekPuzzleBuilder'],
            'TilttekEscapeRoomReservation' => ['TilttekEscapeRoom', 'DefaultAllowed', 'PhilReservation'],
        ],
    ],
    'fuzz' => [
        'targets' => [
            'game' => 'tests/Fuzz/game.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
