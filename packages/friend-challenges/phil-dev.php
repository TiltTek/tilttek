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
            'challenge-record' => 'tests/Fuzz/record.php',
            'challenge-lifecycle' => 'tests/Fuzz/lifecycle.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // The members' records in `InMemory*Store::involving()`: once the limit is reached, nothing more is added, so
            // going on through the rest of the records gives the same list as stopping.
            'Break_' => ['ignore' => ['Tilttek\\FriendChallenges\\Store\\InMemoryChallengeStore::involving']],
            // The settlement recorded of a hold only ever meets another one of the same form, or `cancelled`: the `paid:` that
            // opens it tells nothing more, in whatever order the parts come.
            'Concat' => ['ignoreSourceCodeByRegex' => ['.*\\$this->settles\\(\\$hold, \'paid:\'.*']],
            'ConcatOperandRemoval' => ['ignoreSourceCodeByRegex' => ['.*\\$this->settles\\(\\$hold, \'paid:\'.*']],
            // A hold paid in full gives back 0 points, which `fund()` adds without any change.
            'LessThan' => ['ignoreSourceCodeByRegex' => ['.*if \\(\\$paid < \\$hold->amount\\) \\{']],
            // The pairs of friends and the proofs already met are read by `isset()`: the value `true` is any value that is not `null`.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\\$this->pairs\\[.*\\] = true;', '.*\\$seen\\[\\$proof->id\\] = true;']],
            // The moderators are only searched with `in_array()`: their keys tell nothing.
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['.*\\$this->moderators = array_values\\(array_map\\(.*']],
        ],
    ],
];
