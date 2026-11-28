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
            'file-name' => 'tests/Fuzz/file-name.php',
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // `Axial::rotateAround()` and `Cell::rotateAround()`: a turn of six sixths, or of four quarters, is the
            // identity, so a count raised by a whole number of turns gives the same position, only after more steps.
            'Modulus' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$turns = \\(\\(\\$sixthTurns % 6\\) \\+ 6\\) % 6;',
                    '.*\\$turns = \\(\\(\\$quarterTurns % 4\\) \\+ 4\\) % 4;',
                ],
            ],
            // The nudges of `Axial::walk()` and `Movement::walk()`: they only break the ties of a line along an edge, and
            // a nudge of 0 or 2, or of the other sign, breaks them as a nudge of 1 does — checked on every line of up to
            // forty steps from the origin, for the hexagons, and by the tie of `x` and `y` for the cells, which a nudge
            // of the wrong sign alone changes.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*self::NUDGE \\+ 1,.*',
                    '.*self::NUDGE - 3,.*',
                    // `MapHints::fromFileName()`: a match starts with its first digits, which `(int)` reads alone.
                    '.*\\$value = \\(int\\) \\$match\\[1\\];',
                    '.*\\[\\$columns, \\$rows\\] = \\[\\(int\\) \\$match\\[1\\], \\(int\\) \\$match\\[2\\]\\];',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*self::NUDGE \\+ [12],.*',
                    '.*self::NUDGE - 3,.*',
                ],
            ],
            'Plus' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->q \\* \\$steps \\+ \\$dq \\* \\$step\\) \\* self::NUDGE \\+ 1,.*'],
            ],
            // `Ratio`: the denominator of a ratio is never zero; a numerator of zero is exact, so the sign of the value
            // is never read; a half that is not zero is positive or negative; and a term is never the smallest integer,
            // so a sum cannot reach it from a term of zero.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$this->numerator > 0, \\$mode\\);',
                    '.*0 !== \\$half \\? \\$half > 0 : match.*',
                    '.*\\(\\$b > 0 && \\$a > \\\\PHP_INT_MAX - \\$b\\).*',
                ],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*if \\(\\$denominator < 0\\) \\{',
                    '.*\\(\\$b < 0 && \\$a < -\\\\PHP_INT_MAX - \\$b\\).*',
                ],
            ],
        ],
    ],
];
