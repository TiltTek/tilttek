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
            // `compare()` only orders: a joker is before every other face and level with another joker, whatever the
            // second place, however many places the pair has, and whichever number below 0 comes first.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*return \\[-1, 0\\];.*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*return \\[-1, 0\\];.*'],
            ],
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*return \\[-1, 0\\];.*'],
            ],
            // A draw takes at least one card from a pile that holds at least as many, so the best of them always
            // exists: the exception is never thrown, and a mutant that no longer throws it gives the same result.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => ['.*The draw pile is empty.*'],
            ],
        ],
    ],
];
