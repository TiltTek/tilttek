<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        'translations' => 'blocking',
        // The terms of heraldry, and the French blazons the English documentation quotes.
        'words' => [
            'Armoiries',
            'Xoshiro',
            'accompagné',
            'accompagnée',
            'accosté',
            'bande',
            'bendlet',
            'besants',
            'billette',
            'brochant',
            'bâton',
            'chargé',
            'fasce',
            'fleur',
            'fleurs',
            'golpe',
            'gueules',
            'l’étoile',
            'même',
            'plein',
            'pomme',
            'posés',
            'pourpre',
            'rangés',
            'sautoir',
            'sinople',
            'taillé',
            'torteau',
            'tourteau',
            'tourteaux',
            'tranché',
            'écartelé',
            'étoile',
            'étoiles',
        ],
    ],
    'fuzz' => [
        'targets' => [
            'drawing' => 'tests/Fuzz/drawing.php',
            'seed' => 'tests/Fuzz/seed.php',
        ],
    ],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // Every tincture is blazoned from an ASCII "d": "d’or", "de gueules", so a byte and a character are one.
            'MBString' => ['ignoreSourceCodeByRegex' => ['.*return \'D\'\\.mb_substr\\(.*']],
            // The spots of an ordinary are built from lists, which `array_map` keeps as lists.
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['.*return array_values\\(array_map\\(static fn \\(array \\$spot\\).*']],
            // The ermine pattern is the first to be written: `$svg` is still empty, so `=` and `.=` give the same text.
            'Assignment' => ['ignoreSourceCodeByRegex' => ['.*\\$svg \\.= \'<pattern id="\'\\.\\$id\\.\'-ermine".*']],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
