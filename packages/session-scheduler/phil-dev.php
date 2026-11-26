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
            // The sets of seen members and of taken days are read with `isset()`, which is true for a key whose value is
            // `false` as well as `true`: only the key counts, not the value stored under it.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$seen\\[\\$member\\] = true;', '.*\\$taken\\[.*->format\\(.Y-m-d.\\)\\] = true;'],
            ],
            // A moment asked from itself to itself is at most one occurrence: the occurrences are distinct and rise, so a
            // limit of 2 returns the same single one.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*return \\[\\] !== \\$this->recurrence\\(\\)->between\\(\\$moment, \\$moment, 1\\);'],
            ],
        ],
    ],
];
