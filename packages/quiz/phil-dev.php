<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['PIN', 'PINs']],
    'fuzz' => [
        'targets' => [
            'pin' => 'tests/Fuzz/pin.php',
            'pseudonym' => 'tests/Fuzz/pseudonym.php',
        ],
        'corpus' => 'tests/Fuzz/corpus',
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => [
                // The standings are in rank order: those of the top are their first ones, whose keys already run from 0.
                '.*return array_values\\(array_filter\\(\\$this->standings, .*',
                // The right choices are only counted and intersected with the picks: their keys tell nothing.
                '.*\\$correct = array_values\\(array_unique\\(\\$correct\\)\\);',
                // The constructor reads the values of the lists only.
                '.*return new self\\(\\[\\.\\.\\.array_values\\(\\$this->words\\), .*',
            ]],
            // `shown()` runs in the `Question` state only, which `next()` enters once it set `shownAt`.
            'Throw_' => ['ignoreSourceCodeByRegex' => ['.*return \\$quiz->timer\\(\\$this->position, \\$this->shownAt \\?\\? throw .*']],
            // `Normalizer::normalize()` and `preg_replace()` fail only on text that is not UTF-8, which is refused before.
            'CastString' => ['ignoreSourceCodeByRegex' => [
                '.*\\$decomposed = \\(string\\) Normalizer::normalize\\(.*',
                '.*\\$text = \\(string\\) preg_replace\\(.*',
            ]],
        ],
    ],
];
