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
            'rule-text' => 'tests/Fuzz/rule-text.php',
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // `Lcs::pairs()`: the table is read only at the positions inside its bounds, so a spare row or column changes
            // nothing, and a start value other than 0, or a gain of 2 per match, keeps the same ordering of the
            // lengths on every pair of sequences — checked on 300 000 random pairs of up to twelve items from two to five
            // letters.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$length = array_fill\\(0, \\$rows \\+ 1, array_fill\\(0, \\$columns \\+ 1, 0\\)\\);',
                    '.*\\? \\$length\\[\\$i \\+ 1\\]\\[\\$j \\+ 1\\] \\+ 1',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$length = array_fill\\(0, \\$rows \\+ 1, array_fill\\(0, \\$columns \\+ 1, 0\\)\\);',
                    // The length at a mismatch is the larger of the two neighbours, so `$length[$i][$j]` is never under
                    // `$length[$i + 1][$j]`, and comparing with it is comparing with `$length[$i][$j + 1]`.
                    '.*\\} elseif \\(\\$length\\[\\$i \\+ 1\\]\\[\\$j\\] >= \\$length\\[\\$i\\]\\[\\$j \\+ 1\\]\\) \\{',
                ],
            ],
        ],
    ],
];
