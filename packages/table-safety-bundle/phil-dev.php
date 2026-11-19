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
            // The prompts of the written questions, in `TableSettingsType::data()`: only a question with a prompt is
            // written, so the prompt is never null; the cast only narrows the type for the reader.
            // The theme keys of the answers, in `ThemeAnswers::values()`: PHP turns a numeric key into an integer
            // whether the cast is written or not.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*static fn \\(Question \\$question\\): string => \\(string\\) \\$question->prompt.*',
                    '.*\\$values\\[\\(string\\) \\$theme\\] = \\$answer->value;',
                ],
            ],
            // The tools of the submitted data, in `TableSettingsType::settings()`: `TableSettings` keeps the tools of
            // `SafetyTool::cases()` that the list holds, in their order, so a stray value or a gap in the keys never shows.
            'UnwrapArrayFilter' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$tools = array_values\\(array_filter\\(.*',
                ],
            ],
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$tools = array_values\\(array_filter\\(.*',
                ],
            ],
            // The last check-in, in `DbalSignalStore::lastCheckIn()`: only the first row read is kept, so a limit of two
            // rows reads the same check-in as a limit of one.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\$checkIn = \$this->where\(.*, 1\)\[0\] \?\? null;',
                ],
            ],
            // The domain of the options of a written question: `true` stands for the domain of the field, which is
            // `false` for a written question.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\'choice_translation_domain\' => \\$question->isWritten\\(\\) \\? false : .*',
                ],
            ],
        ],
    ],
];
