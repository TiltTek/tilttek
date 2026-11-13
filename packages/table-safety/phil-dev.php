<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['Fatland', 'Germain', 'Jágr', 'Kienna', 'Quade', 'Stavropoulos', 'TTRPG']],
    'infection' => [
        'mutators' => [
            // The keys of stored answers, options, questions and added themes: PHP turns a numeric key into an integer
            // whether the cast is written or not; the cast only narrows the type of the key for the reader.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$answers\\[\\(string\\) \\$question\\] = .*',
                    '.*\\$options\\[\\(string\\) \\$option\\] = \\\\is_string\\(\\$label\\) \\? \\$label : null;',
                    '.*isset\\(\\$this->questions\\[\\(string\\) \\$key\\]\\).*',
                    '.*\\$themes\\[\\(string\\) \\$key\\] = \\\\is_string\\(\\$label\\) \\? .*',
                ],
            ],
            // The option keys of a tally: `array_fill_keys()` gives the same integer key to "1" and to 1.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_fill_keys\\(array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$question->options\\)\\), 0\\);',
                ],
            ],
            // Lists rebuilt by their consumer: the constructor of `StarsAndWishes` keeps its texts with `$kept[]`, that of
            // `Template` indexes its questions by key, and `usort()` renumbers the signals.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$list = static fn \\(mixed \\$texts\\): array => .*',
                    '.*return new self\\(\\$this->key, array_values\\(.*',
                    '.*\\$signals = array_values\\(array_filter\\(.*',
                ],
            ],
            // The options a choice question keeps: `isset()` is true for a value of false as for one of true.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$chosen\\[\\$option\\] = true;',
                ],
            ],
            // The label of a theme: an added theme never shares a key with the catalogue, so the order of the two
            // lookups does not show.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return \\$this->custom\\[\\$theme\\] \\?\\? \\$this->catalogue->theme.*',
                ],
            ],
        ],
    ],
];
