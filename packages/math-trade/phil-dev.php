<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'fuzz' => [
        // The JSON reader on documents nobody wrote, from the seeds of tests/Fuzz/corpus/<target>: whatever it reads,
        // it returns a trade list that writes back to itself, or refuses it with an exception of the library.
        'targets' => [
            'trade-list-json' => 'tests/Fuzz/trade-list-json.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // `MinCostAssignment::solve()` and `TradeSolver::solve()`: the rows are indexed 0 to n - 1 once sorted by key
            // — each row of the matrix is assigned, and each node has a row — so `array_values()` returns the list it
            // is given.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return array_values\\(\\$assignment->columnOf\\);',
                    '.*MinCostAssignment::solve\\(array_values\\(\\$edges\\)\\);',
                ],
            ],
            // Sets whose members are only looked up with `isset()` or counted, never read: `true` or `false` is the same
            // member — the offers a wish names, the participants, the columns settled, the wishers and the copies of a
            // game, the nodes of a cycle already seen.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$seen\\[(\\$offer|\\$participant->id|\\$node)\\] = true;',
                    '.*\\$settled\\[\\$column\\] = true;',
                    '.*\\$(wishers|copies)\\[\\$game\\]\\[.*\\] = true;',
                ],
            ],
            // `TradeSolver::graph()`: the rows of `$wishes` are read by node and the edges are sorted by row afterwards.
            'FunctionCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*ksort\\(\\$wishes\\);'],
            ],
            // `TradeSolver::graph()`: an offer names another once at most — `Offer` refuses a second wish for it — so the
            // cost of a direct wish is never assigned twice.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => ['.*\\$costs\\[\\$to\\] \\?\\?= \\$position \\+ 1;'],
            ],
            // `MinCostAssignment::augment()`: a first pop of a column is its distance, and later ones are of a settled
            // column, so the second operand never decides alone, and `&&` skips the same entries.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['.*isset\\(\\$settled\\[\\$column\\]\\) \\|\\| \\$length > \\$distance\\[\\$column\\]\\) \\{'],
            ],
            // `TradeListJson::object()`: `array_diff()` compares the keys as strings, and the first one found is written
            // into the message as a string.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => ['.*array_diff\\(array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$value\\)\\), \\$keys\\);'],
            ],
            // The start of a search in `MinCostAssignment::augment()` shifts every length of the search by the same
            // amount, which the potentials cancel; and the cost of keeping an offer in `TradeSolver::solve()` only has to
            // exceed the sum of every priority, which 2 + the wishes does as 1 + the wishes does.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->relax\\(\\$start, 0, 0, .*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$this->relax\\(\\$start, 0, 0, .*',
                    '.*\\$keep = 1;',
                ],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['Azul', 'Catan']],
];
