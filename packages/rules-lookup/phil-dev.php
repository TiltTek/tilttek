<?php

declare(strict_types=1);

use Tilttek\RulesLookup\Store\InMemoryRuleStore;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'fuzz' => [
        'targets' => [
            'query' => 'tests/Fuzz/query.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The postings that narrow the rules to read, in `InMemoryRuleStore::candidates()`: every rule it keeps is
            // scored again word by word in `search()`, in the locale the reader sees, so a wider set of candidates — all
            // the rules, one word's rules, no intersection — gives the same results, only slower.
            'Foreach_' => ['ignore' => [InMemoryRuleStore::class.'::candidates']],
            'UnwrapArrayIntersectKey' => ['ignore' => [InMemoryRuleStore::class.'::candidates']],
            // Only the keys of the postings are read, never their values.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$postings\\[\\$word\\]\\[\\$id\\] = true;'],
            ],
            // The rules of a store are rebuilt by the constructor, which indexes them by their identifier: the keys of
            // the list given to it are never read.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*new self\\(\\.\\.\\.array_values\\(\\$all\\)\\);'],
            ],
            // The lower bound of a binary search that skips the word equal to the prefix: that word is left out of
            // the expansion anyway.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['.*strcmp\\(\\$this->vocabulary\\[\\$middle\\], \\$prefix\\) < 0.*'],
            ],
            // A text that is plain ASCII goes through the normalisation to the same text: the early return is only a
            // faster way to lowercase it.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*return strtolower\\(\\$text\\);'],
            ],
            // `preg_replace()` returns null only on an error of the pattern or of the subject: the subject is the output of
            // the normaliser, valid UTF-8, and the pattern a fixed one, so the cast only satisfies the types.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\$bare = \\(string\\) preg_replace\\(.*'],
            ],
            // A rule identifier ends the pattern with `$` and `D`: its last part accepts a line break, so a trailing
            // one is read by the pattern itself and the flag changes nothing.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['.*preg_match\\(.*\\$/D., \\$id, \\$parts\\).*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
