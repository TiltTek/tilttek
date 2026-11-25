<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'docs' => ['public' => true, 'words' => ['Mercure']],
    'paths' => ['src', 'tests'],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // The keys of the rooms are strings: the constructor hands each to `Room`, which takes a string.
            'UnwrapArrayMap' => ['ignoreSourceCodeByRegex' => [
                '.*return array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$this->rooms\\)\\);',
                // The drivers give the identifiers back as strings; `Rows::text()` only tells the analyser so.
                '.*return array_values\\(array_map\\(Rows::text\\(\\.\\.\\.\\), \\$ids\\)\\);',
            ]],
            // The result of an escaped game is written again by the move that follows the consent, with the same flag:
            // the store is told as well, so that a store of the application never depends on that order.
            'MethodCallRemoval' => ['ignoreSourceCodeByRegex' => ['.*\\$this->results->consent\\(\\$id, \\$public\\);']],
            // PHPStan reads a mapped result as an array, and these two must be lists: the rows come as lists already.
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => [
                '.*return array_values\\(array_map\\(Rows::text\\(\\.\\.\\.\\), \\$ids\\)\\);',
                '.*return array_values\\(array_map\\(\\$this->result\\(\\.\\.\\.\\), \\$rows\\)\\);',
                // `array_chunk()` hands out lists whatever the keys it is given: `array_unique()` leaves holes only before it.
                '.*foreach \\(array_chunk\\(array_values\\(array_unique\\(.*',
            ]],
            'CastInt' => ['ignoreSourceCodeByRegex' => [
                // The column holds integers: a database turns the string of the microseconds into the integer it stores.
                '.*\'escaped_micro\' => \\(int\\) \\$result->escapedAt->format\\(\'u\'\\),',
                // The count of a deletion comes as an integer or a numeric string, and `+=` turns both into the same integer.
                '.*\\$deleted \\+= \\(int\\) \\$this->connection->executeStatement\\(\'DELETE FROM.*',
            ]],
        ],
    ],
];
