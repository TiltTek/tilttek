<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['bringable', 'preordered']],
    'infection' => [
        'mutators' => [
            // The cells go through `array_map()`, which calls `cell()` without strict types: an integer reaches its
            // `string` parameter as the digits it spells, so each cast only states what the shape of the rows says.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\(string\\) \\(\\$rank \\+ 1\\).*',
                    '.*\\(string\\) \\$wish->level->value.*',
                    '.*\\(string\\) \\$note->(rating|plays).*',
                ],
            ],
            // Whoever reads the lists goes through them in order, and the constructor of a shelf keys its lists again:
            // the keys these calls drop or keep are never seen.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_values\\(array_unique\\(\\$members\\)\\).*',
                    '.*new Shelf\\(\\$shelf->member, array_values\\(\\$copies\\).*',
                    '.*array_values\\(\\$(copies|wishes|notes) \\?\\? \\$this->(copies|wishes|notes)\\),.*',
                ],
            ],
            // `isset()` is true for `false` as for `true`: the value only marks the game as given.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$given\\[\\$copy->game\\] = true;.*',
                    '.*\\$games\\[\\$entry->id\\] = true;.*',
                    '.*\\$kept\\[\\$item->entry\\] = true;.*',
                ],
            ],
            // The other wish is on the shelf, so `array_search()` finds its position: the cast only gives PHPStan the
            // `int` of a shelf kept by game, and a position is the same number with or without it.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\(int\\) array_search\\(\\$other.*'],
            ],
        ],
    ],
];
