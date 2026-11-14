<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['OpenSkill', 'Plackett', 'Luce', 'Thurstone', 'Mosteller', 'Elo', 'Glicko', 'Maclaurin', 'Weng', 'Chih']],
    'fuzz' => [
        'targets' => [
            'rating' => 'tests/Fuzz/rating.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // The keys that mark what was seen or what is allowed, in `GameResult`, `Ratings` and the `restore()` of each
            // system: `isset()` and `array_diff_key()` read the key alone, so the value `true` or `false` changes nothing.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$seen\\[.*\\] = true;',
                    // The results counted, in `InMemoryRatingStore::save()`: `isset()` reads the key alone.
                    '.*\\$this->results\\[\\$game\\]\\[\\$result\\] = true;',
                    '.*array_diff_key\\(\\$values, .*',
                ],
            ],
            // The games of a member the ratings do not hold, in `EloSystem::rate()`: a step of the K-factor applies before
            // one game at least, so -1 games and 0 games are before the same steps.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$games = \\$current\\[\\$member\\]->games \\?\\? 0;',
                    '.*for \\(\\$n = 60; \\$n >= 1; --\\$n\\) \\{',
                ],
            ],
            // The sum of the means of a side, in `OpenSkillSystem::rate()`: every side starts from the same offset, and
            // only the differences between the means count.
            'OneZeroFloat' => [
                'ignoreSourceCodeByRegex' => ['.*\\$means\\[\\$side\\] = 0\\.0;'],
            ],
            // The error function, in `Gaussian`: its series ends on the size of its terms, 1e-46 by the 60th at most
            // below 2, so its bound and its `&&` change no digit; the fraction of the tail is as deep at 59 or 61 as at 60
            // to the last digit of a double; the two ways meet at 2 to that digit; a mass under 1e-300 means the draw
            // margin and the difference are both zero, where `-x - t` and `-x + t` are both zero.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*if \\(\\$x < 2\\) \\{',
                    '.*if \\(\\$mass < 1e-300\\) \\{',
                    '.*return \\[\\$x < 0 \\? -\\$x - \\$t : -\\$x \\+ \\$t, 1\\.0\\];',
                ],
            ],
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['.*for \\(\\$n = 1; \\$n <= 60 && abs\\(\\$term\\) > 1e-17; \\+\\+\\$n\\) \\{'],
            ],
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['.*for \\(\\$n = 1; \\$n <= 60 && abs\\(\\$term\\) > 1e-17; \\+\\+\\$n\\) \\{'],
            ],
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['.*for \\(\\$n = 1; \\$n <= 60 && abs\\(\\$term\\) > 1e-17; \\+\\+\\$n\\) \\{'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*for \\(\\$n = 60; \\$n >= 1; --\\$n\\) \\{'],
            ],
        ],
    ],
];
