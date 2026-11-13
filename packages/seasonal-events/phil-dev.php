<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'deptrac' => [
        // The core is plain PHP on phil/recurrence and phil/resource; each optional bridge, in its namespace, alone sees
        // the package it bridges to.
        'layers' => [
            'TilttekSeasonalEvents' => '^Tilttek\\\\SeasonalEvents\\\\(?!Bridge\\\\).*',
            'TilttekSeasonalEventsBridge' => '^Tilttek\\\\SeasonalEvents\\\\Bridge\\\\.*',
        ],
        'ruleset' => [
            'TilttekSeasonalEvents' => ['DefaultAllowed', 'PhilRecurrence', 'PhilResource'],
            'TilttekSeasonalEventsBridge' => ['TilttekSeasonalEvents', 'DefaultAllowed', 'PhilResource', 'TilttekQuests', 'TilttekCollections'],
        ],
    ],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => [
                // A wider window only adds starts whose period ended before the moment, which `contains()` turns down.
                '.*array_reverse\\(\\$this->startsBetween\\(\\$day - \\$this->days \\+ 1, \\$day\\)\\).*',
            ]],
            // Below the year 0, `intdiv()` truncates toward zero: -399 / 400 and 0 / 400 are both era 0.
            'GreaterThanOrEqualTo' => ['ignoreSourceCodeByRegex' => ['.*\\$era = intdiv\\(\\$year >= 0 \\? \\$year : \\$year - 399, 400\\);']],
            // `array_diff()` compares as strings: the keys need no cast to be told from the known ones.
            'UnwrapArrayMap' => ['ignoreSourceCodeByRegex' => ['.*\\$unknown = array_diff\\(array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$definition\\)\\), \\$known\\);']],
            // The shortcut of a plain rule and the general search find the same starts: leaving the shortcut changes nothing.
            'ReturnRemoval' => ['ignoreSourceCodeByRegex' => ['.*return \\$this->sameDateBetween\\(\\$this->everyYears, \\$fromDay, \\$throughDay\\);']],
            'Plus' => ['ignoreSourceCodeByRegex' => ['.*array_reverse\\(\\$this->startsBetween\\(\\$day - \\$this->days \\+ 1, \\$day\\)\\).*']],
        ],
    ],
    'fuzz' => [
        'targets' => [
            'seasons' => 'tests/Fuzz/seasons.php',
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
