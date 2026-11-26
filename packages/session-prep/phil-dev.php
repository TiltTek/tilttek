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
            // `withSceneStatus()`, `withSecretRevealed()`, `withClueFound()` and `withItemChecked()` run first and throw
            // `InvalidPreparation` when no part has the key, so the part read on the line after is never missing: the
            // null-safe access only states the `null` that `scene()`, `secret()`, `clue()` and `item()` may return.
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => ['.*\\$before->(scene|secret|item)\\(.*\\)\\?->.*', '.*\\$before->secret\\(\\$secret\\)\\?->clue\\(\\$clue\\)\\?->found.*'],
            ],
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => ['.*\\$before->secret\\(\\$secret\\)\\?->clue\\(\\$clue\\)\\?->found.*'],
            ],
        ],
    ],
];
