<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // The key-value store a shared cache pool may live on.
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['Valkey']],
    'infection' => [
        'mutators' => [
            // The count of moves, in `VerifyCommand`: the table of the definition list prints an integer and its text
            // alike, so the cast only serves the types of PHPStan's rules.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\'Moves\' => \\(string\\) \\$log->sequence\\(\\).*',
                    // The payload of a move, in `DbalLogStore::moves()`: PHP turns a numeric text key into the integer key
                    // the foreach already gives, so the cast only serves the types of PHPStan's rules.
                    '.*\\$payload\\[\\(string\\) \\$name\\] = \\$value;',
                ],
            ],
            // The first move read, in `DbalLogStore::load()`: `moves()` starts at the first move whatever lower number it
            // is given, so 1 and 0 ask for the same window.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$moves = \\[\\.\\.\\.\\$this->moves\\(\\$gameId, 1, \\$header->sequence\\)\\];'],
            ],
        ],
    ],
];
