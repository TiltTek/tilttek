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
            // Numeric strings sort by their value: the cast only states that the microseconds are a number.
            'CastInt' => ['ignoreSourceCodeByRegex' => ['.*\$micros\[\] = \(int\) .*']],
            // The sort below renumbers the entries it keeps: array_values() only states, for the analysis, that they form a list.
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['.*\\$entries = array_values\\(array_filter\\(.*']],
            // With nothing in excess the loop below ends on its first turn: the early return only spares a read of the inbox.
            'ReturnRemoval' => ['ignoreSourceCodeByRegex' => ['.*return;.*']],
        ],
    ],
];
