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
            'tally' => 'tests/Fuzz/tally.php',
        ],
    ],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // A statistic of zero gives a front factor of exp(−∞) = 0, so the tail is 1 by the formula as well.
            'LessThanOrEqualTo' => ['ignoreSourceCodeByRegex' => ['.*if \\(\\$statistic <= 0\\.0\\) \\{']],
            // The series and the continued fraction both give the tail to full precision on either side of a + 1:
            // where one takes over from the other changes the work done, never the value beyond its last digit.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => [
                '.*if \\(\\$x < \\$a \\+ 1\\) \\{',
                // ln 1 = 0, so starting the sum at 1 or 2 adds the same zero.
                '.*for \\(\\$i = 2; \\$i < \\$degreesOfFreedom / 2; \\+\\+\\$i\\) \\{',
                // For an odd k, i < (k − 1) / 2 and i < (k − 2) / 2 hold for the same whole numbers.
                '.*for \\(\\$i = 0; \\$i < \\(\\$degreesOfFreedom - 1\\) / 2; \\+\\+\\$i\\) \\{',
                // Lentz's method starts from any large value: it is replaced at the first step.
                '.*\\$c = 1 / self::TINY;',
                '.*if \\(abs\\(\\$step - 1\\) < self::PRECISION\\) \\{',
            ]],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => [
                '.*if \\(\\$x < \\$a \\+ 1\\) \\{',
                '.*for \\(\\$i = 2; \\$i < \\$degreesOfFreedom / 2; \\+\\+\\$i\\) \\{',
                '.*if \\(abs\\(\\$step - 1\\) < self::PRECISION\\) \\{',
            ]],
            'LessThan' => ['ignoreSourceCodeByRegex' => [
                '.*if \\(\\$x < \\$a \\+ 1\\) \\{',
                // The bounds of the loops and the precision at which they stop only bound the work: a later stop
                // adds terms of 10⁻¹⁵ of the sum or steps of 1 ± 10⁻¹⁵, and the cap is never reached.
                '.*for \\(\\$n = 1; \\$n < self::MAX_TERMS && abs.*',
                '.*for \\(\\$i = 1; \\$i < self::MAX_TERMS; \\+\\+\\$i\\) \\{',
                '.*if \\(abs\\(\\$step - 1\\) < self::PRECISION\\) \\{',
                // A value of exactly TINY is replaced by TINY: the same number, up to a sign no one can see.
                '.*\\$d = abs\\(\\$d\\) < self::TINY.*',
                '.*\\$c = abs\\(\\$c\\) < self::TINY.*',
                // Two probabilities that compare equal are the same fraction, whichever is kept.
                '.*\\|\\| \\$probability->compare\\(\\$rarest\\) < 0\\) \\{',
            ]],
            'GreaterThan' => ['ignoreSourceCodeByRegex' => ['.*for \\(\\$n = 1; \\$n < self::MAX_TERMS && abs.*']],
            'LogicalAnd' => ['ignoreSourceCodeByRegex' => ['.*for \\(\\$n = 1; \\$n < self::MAX_TERMS && abs.*']],
            'Minus' => ['ignoreSourceCodeByRegex' => ['.*if \\(abs\\(\\$step - 1\\) < self::PRECISION\\) \\{']],
            'Break_' => ['ignoreSourceCodeByRegex' => ['.*break;']],
            // The cast keeps the analysis content; a verdict that has a p-value never has a null one.
            'CastFloat' => ['ignoreSourceCodeByRegex' => ['.*round\\(1 / \\(float\\) \\$result->pValue\\).*']],
            // 1 / 0.001 is exactly 1000: rounding it any way gives the same whole number.
            'RoundingFamily' => ['ignoreSourceCodeByRegex' => ['.*round\\(1 / Verdict::VERY_UNUSUAL_BELOW\\).*']],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
