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
            // The read that counts a giver's thanks takes twice the cap: a cancellation follows the thanks it cancels,
            // so those entries hold the cap's count of thanks or every entry. A wider read counts the same, only
            // slower.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->journal->read\\(0, 2 \\* \\$max, \\$filter\\).*'],
            ],
        ],
    ],
];
