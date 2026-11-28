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
            // `isset()` is true for `false` as for `true`: the value only marks the key as seen, or the faction as shown.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$seen\\[\\$key\\] = true;.*',
                    '.*\\$shown\\[\\$faction->key\\] = true;.*',
                ],
            ],
            // `Factions::endRelation()` reads the relation to write its entry: without the throw, `withoutRelation()`
            // refuses the same pair, as the same exception with the same message, before anything is kept.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => ['.*\\$held = \\$before->relation\\(\\$faction, \\$other\\) \\?\\? throw .*'],
            ],
        ],
    ],
];
