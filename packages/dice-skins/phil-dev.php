<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The adapter under DiceBundle\ implements the port of tilttek/dice-bundle, suggested: it is a layer of its own,
        // the only one that sees the application layer of the bundle; the rest of the library never sees it.
        'layers' => [
            'TilttekDiceSkins' => ['^Tilttek\\\\DiceSkins\\\\(?!DiceBundle\\\\).*'],
            'TilttekDiceSkinsDiceBundle' => ['^Tilttek\\\\DiceSkins\\\\DiceBundle\\\\.*'],
        ],
        'ruleset' => [
            'TilttekDiceSkins' => ['DefaultAllowed', 'PhilResource', 'TilttekCollections'],
            'TilttekDiceSkinsDiceBundle' => ['TilttekDiceSkins', 'DefaultAllowed', 'PhilResource', 'TilttekDiceBundleApplication'],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['WCAG', 'sRGB']],
    'fuzz' => [
        'targets' => [
            'definitions' => 'tests/Fuzz/definitions.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // A key of an array holding digits is an integer whichever way it is written.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\$types\\[\\(string\\) \\$sides\\] = \\$look->toArray\\(\\);'],
            ],
            // A number of sides is 2 or more: a choice for all the dice sorts first whether it counts as 0, -1 or 1.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*usort\\(\\$choices, .*\\$a->sides \\?\\? 0.*'],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*usort\\(\\$choices, .*\\$a->sides \\?\\? 0.*'],
            ],
            // The slot is a key of the store alone: "d20" and "20d" tell the numbers of sides apart the same way.
            'Concat' => [
                'ignoreSourceCodeByRegex' => ['.*return null === \\$sides \\? self::ALL : \'d\'\\.\\$sides;'],
            ],
            // Comparisons of a ratio or a channel with a threshold no colour of 8 bits per channel reaches exactly: the
            // linear threshold of 0.04045 falls between 10/255 and 11/255, and no colour stands out from black and from
            // white at the same ratio, nor at exactly 7:1 from another, so `<` and `<=` give the same results.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->contrastWith\\(\\$white\\) > \\$this->contrastWith\\(self::black\\(\\)\\).*'],
            ],
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['.*\\$value <= 0\\.04045.*'],
            ],
            'GreaterThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['.*return \\$this->contrast\\(\\) >= \\$ratio;'],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['.*if \\(\\$face->contrastWith\\(\\$ink\\) < Color::AAA\\).*'],
            ],
        ],
    ],
];
