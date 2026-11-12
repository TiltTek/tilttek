<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // The key-value store whose pools keep the states of the tables with a memory.
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['Valkey']],
    'infection' => [
        'mutators' => [
            // The count of a draw that is not a whole number: 0 or -1, both are below 1 and refused the same.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\? \\(int\\) \\$count : 0;',
                ],
            ],
            // The paths of the documents kept in cache by `CachedCatalog`: PHP turns a numeric key into an integer
            // whether the cast is written or not.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$documents\\[\\(string\\) \\$source\\] = \\$document;',
                ],
            ],
        ],
    ],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : constructeur privé d'une exception à constructeurs nommés, plus étroit que celui du parent.
            ['identifier' => 'symplify.parentMethodVisibilityOverride', 'path' => 'src/Application/Exception/AccessDenied.php'],
        ],
    ],
];
