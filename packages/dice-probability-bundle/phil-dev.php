<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'docs' => ['public' => true],
    'paths' => ['src', 'tests'],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // The limbs bound only the work: a term past them is longer than twice the digits and the next check refuses
            // it all the same, so a looser or tighter bound changes the time taken, never the answer.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*\\$limbs = intdiv\\(2 \\* \\$digits, 9\\) \\+ 1;']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*\\$limbs = intdiv\\(2 \\* \\$digits, 9\\) \\+ 1;']],
            'LogicalOr' => ['ignoreSourceCodeByRegex' => ['.*\\$fraction->numerator->size\\(\\) > \\$limbs \\|\\|.*']],
            'ReturnRemoval' => ['ignoreSourceCodeByRegex' => ['.*return null; // too long, as the limbs say']],
            // The second expression is computed only when the first has a distribution, so at most one failure is listed.
            'ArrayOneItem' => ['ignoreSourceCodeByRegex' => ['.*return \\$failures;']],
            // The integer is read back as text by the concatenation that follows: an int and its string read the same.
            'CastString' => ['ignoreSourceCodeByRegex' => ['.*return \\[\\(string\\) intdiv\\(\\$top, \\$a\\).*']],
        ],
    ],
];
