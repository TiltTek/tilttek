<?php

declare(strict_types=1);

use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\ArmyValidator;
use Tilttek\WargameArmy\Detachment;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'fuzz' => [
        'targets' => [
            'army-catalogue' => 'tests/Fuzz/army-catalogue.php',
            'army-list' => 'tests/Fuzz/army-list.php',
        ],
    ],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // `sprintf('%s')` writes an integer as its digits, and a numeric string and its integer are the same array key.
            'CastString' => ['ignoreSourceCodeByRegex' => [
                '.*\\$range = \\$unit->minModels === \\$unit->maxModels \\? \\(string\\) \\$unit->minModels : .*',
                '.*\\$counts\\[\\(string\\) \\$name\\] = \\$count;',
                '.*\\[\'category\' => \\(string\\) \\$this->category\\].*',
            ]],
            // `isset()` is true for a key holding false as well: the flag is read by its key alone.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\\$categories\\[\\$slot->category\\] = true;']],
            // Without `D`, `$` also matches before a final line feed, which `trim()` refuses on the next test.
            'PregMatchRemoveFlags' => ['ignoreSourceCodeByRegex' => ['.*preg_match\\(\'/\\^\\[\\^\\\\p\\{Cc\\}\\]\\+\\$/Du\'.*']],
            // The positions are those of a list, which `list<>` types: replacing one of them leaves a list, and the
            // constraints are only walked in order, whatever their keys.
            'UnwrapArrayValues' => [
                'ignore' => [
                    ArmyList::class.'::withDetachmentAt',
                    Detachment::class.'::withUnitAt',
                    ArmyValidator::class.'::__construct',
                ],
            ],
            // A selector of a unit takes the same units whether it is answered at once or by walking the units counted.
            'ReturnRemoval' => ['ignoreSourceCodeByRegex' => ['.*return \\$byUnit\\[\\$selector->unit\\] \\?\\? 0;']],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
