<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'docs' => ['public' => true],
    'paths' => ['src', 'tests'],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // `fetchOne()` reads the first row only: a limit of 2 returns the same row.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*->setMaxResults\\(1\\)']],
            // The database coerces the text of a number it is given for an integer column, on every platform: the
            // types name the column's, and no result tells them apart.
            'ArrayItemRemoval' => ['ignoreSourceCodeByRegex' => ['.*\\$connection->insert\\(LuckTokensTables::ENTRIES, self::row\\(\\$entry\\).*']],
            // The refusals of an application without the library: the suite runs with the libraries installed.
            'Identical' => ['ignoreSourceCodeByRegex' => ['.*\'security\' === \\$config\\[\'holder_access\'\\] && !interface_exists.*']],
            'LogicalAndSingleSubExprNegation' => ['ignoreSourceCodeByRegex' => ['.*\\$config\\[\'lock\'\\]\\[\'enabled\'\\] && !class_exists.*']],
        ],
    ],
];
