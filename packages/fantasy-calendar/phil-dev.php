<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'fuzz' => [
        'targets' => [
            'calendar' => 'tests/Fuzz/calendar.php',
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // `Calendar::weekday()`: the month of the day is in the week — the method returned before otherwise — so
            // adding its own length to the days outside the week would add nothing.
            // `Calendar::yearOf()`: the year estimated from the average length of a year only starts the two loops that
            // settle it, so any estimate, and a loop that stops one day later, end on the same year.
            // `Moon::ofDecimalCycle()`: a match starts with its first digits, which `(int)` reads alone.
            // `Moon::nextPhase()`: a window of a whole cycle and a day finds every phase a longer one does.
            // `Calendar::checkSeasons()`: the seasons are compared within a year, so the year of their dates is any.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*for \\(\\$before = 0; \\$before < \\$date->month - 1; \\+\\+\\$before\\) \\{',
                    '.*\\$year = 1 \\+ Limits::floorDiv\\(.*',
                    '.*\\(int\\) \\$match\\[1\\] \\* \\$denominator.*',
                    '.*\\$days = intdiv\\(\\$this->cycleNumerator, \\$this->cycleDenominator\\) \\+ 1;',
                    '.*new Date\\(0, \\$season->month, \\$season->day\\);',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$year = 1 \\+ Limits::floorDiv\\(.*',
                    '.*\\$days = intdiv\\(\\$this->cycleNumerator, \\$this->cycleDenominator\\) \\+ 1;',
                    '.*new Date\\(0, \\$season->month, \\$season->day\\);',
                ],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*for \\(\\$before = 0; \\$before < \\$date->month - 1; \\+\\+\\$before\\) \\{',
                    // `LeapRule::__construct()`: the entry of the period itself is never read, only those before it.
                    '.*for \\(\\$year = 0; \\$year < \\$period; \\+\\+\\$year\\) \\{',
                ],
            ],
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    '.*for \\(\\$day = \\$from; \\$day <= \\$from \\+ \\$days; \\+\\+\\$day\\) \\{',
                    // `CurrentDate::today()`: no second has passed at 0, so the day set is the day returned either way.
                    '.*\\$this->pace->isManual\\(\\) \\|\\| \\$elapsed <= 0.*',
                ],
            ],
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['.*while \\(\\$this->daysBefore\\(\\$year\\) > \\$day->number\\) \\{'],
            ],
            'Multiplication' => [
                'ignoreSourceCodeByRegex' => ['.*\\$periodDays = \\$period \\* \\$this->commonLength.*'],
            ],
            'Plus' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$periodDays = \\$period \\* \\$this->commonLength.*',
                    '.*\\$year = 1 \\+ Limits::floorDiv\\(.*',
                ],
            ],
            // The casts of a start year the eras checked is not null: they tell PHPStan, and PHP reads the integer as is.
            // `Moon::ofDecimalCycle()`: a text of digits multiplies as the integer, and `0` before the decimals only
            // stands for an empty text.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\(int\\) \\$current->startYear.*',
                    '.*\\(int\\) \\$next->startYear.*',
                    '.*\\(int\\) \\$dated\\[0\\]->startYear.*',
                    '.*\\(int\\) \\$match\\[1\\] \\* \\$denominator.*',
                ],
            ],
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*\\(int\\) \\$match\\[1\\] \\* \\$denominator.*'],
            ],
            // `Calendar::unique()`: the identifiers seen are only looked up with `isset()`, which `false` satisfies too.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$seen\\[\\$item->id\\] = true;'],
            ],
        ],
    ],
];
