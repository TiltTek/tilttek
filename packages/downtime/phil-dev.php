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
            // `isset()` is true for `false` as for `true`: the value only marks the identifier as seen.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\\$seen\\[\\$value\\] = true;.*']],
            // A fixed resolution ends with its outcome whatever the total: the 0 is a placeholder the resolution ignores.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*outcomeFor\\(0\\), null.*']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*outcomeFor\\(0\\), null.*']],
        ],
    ],
];
