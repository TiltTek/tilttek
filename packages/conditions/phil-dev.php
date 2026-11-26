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
            // `isset()` is true for `false` as for `true`: the value only marks the instance, or the implied condition,
            // as seen.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$seen\\[\\$condition->id\\] = true;.*',
                    '.*\\$seen\\[\\$implied\\] = true;.*',
                ],
            ],
            // A condition that sums or decays has a value (the definition refuses it otherwise), and so has each of its
            // applications: the casts only give PHPStan the `int` of a value kept as `?int`, and `null` counts for 0 in
            // the arithmetic they guard.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$sum = \\(int\\) \\$first->value \\+ \\(int\\) \\$value;.*',
                    '.*\\$value = \\(int\\) \\$one->value - \\$decay->amount;.*',
                ],
            ],
            // A condition without value is applied without one: both sides are then 0, and a kept value of null reads
            // as 0 or as -1 alike, since the value applied is 0 or more.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$current = \\$first->value \\?\\? 0;.*',
                    // `Conditions::count()`: the seconds are only read when no boundary is counted, and `advanceTime()`
                    // gives them then; the turns and the rounds leave the default unread.
                    '.*\\?string \\$turnOf, int \\$seconds = 0\\).*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\?string \\$turnOf, int \\$seconds = 0\\).*'],
            ],
            // `EffectCalculator::additions()`: an amount of 0 never reaches the key, and swapping the signs of the keys
            // only renames the two groups, a bonus and a penalty, that never meet.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['.*\\$key = \\(\\$applied->amount > 0 \\? \'\\+\' : \'-\'\\)\\.\\$type;.*'],
            ],
            'Ternary' => [
                'ignoreSourceCodeByRegex' => ['.*\\$key = \\(\\$applied->amount > 0 \\? \'\\+\' : \'-\'\\)\\.\\$type;.*'],
            ],
        ],
    ],
];
