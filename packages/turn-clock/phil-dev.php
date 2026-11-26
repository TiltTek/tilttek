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
            // The current player of a clock is one of its players, which the constructor checks: looking them up never
            // finds nothing, so the exception is never thrown, and a mutant that no longer throws it gives the same result.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => ['.*\\$clock->player\\(\\(string\\) \\$clock->current\\) \\?\\? throw.*'],
            ],
        ],
    ],
];
