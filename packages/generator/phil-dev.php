<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // The Spanish word of the grammar's examples, and the exploration of a map of hexagons that the examples draw.
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['hacha', 'hexcrawl']],
    'fuzz' => [
        'targets' => [
            'document' => 'tests/Fuzz/document.php',
            'markov' => 'tests/Fuzz/markov.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The clock in milliseconds: dividing the nanoseconds by one million plus or minus one changes the scale by
            // a millionth, which no test can measure on a time limit. The memory of the draws: `Producer::pool()` finds
            // the entries of a list already filtered by the tags a draw asks for; comparing another field of the memory
            // never finds anything, and the entries are filtered again, the same: only the cost changes.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'return hrtime\\(true\\) / 1_000_000;',
                    '.*\\$pool = array_find\\(\\$this->pools\\[\\$list->name\\] \\?\\? \\[\\], static fn \\(array \\$pool\\): bool => \\$pool\\[0\\] === \\$tags\\);',
                ],
            ],
            // See `IncrementInteger`: the clock in milliseconds.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'return hrtime\\(true\\) / 1_000_000;',
                ],
            ],
            // The memory of the draws (see `IncrementInteger`): without a memory, the entries are filtered again, the
            // same.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$pool = array_find\\(\\$this->pools\\[\\$list->name\\] \\?\\? \\[\\], static fn \\(array \\$pool\\): bool => \\$pool\\[0\\] === \\$tags\\);',
                ],
            ],
            // Sets read by `isset()`, which holds for false as for true: the entries a Markov model knows, the results
            // a unique draw with a state has seen, the words a pick has invented or excluded. And the letters a word at
            // its longest length may end with, filtered by `array_intersect_key()`, which reads only the keys.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$known\\[\\$text\\] = true;',
                    '\\$seen\\[\\$key\\] = true;',
                    '\\$taken\\[\\$key\\] = true;',
                    '.*array_intersect_key\\(\\$followers, \\[self::END => true\\]\\);',
                ],
            ],
            // The state a result leaves, in the order of its definition: every value is given or kept by
            // `Producer::advance()`, so the values the order is taken from are all replaced, empty texts or not.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    'return array_replace\\(array_map\\(static fn \\(\\): string => \'\', \\$definition->state\\), \\$current\\);',
                ],
            ],
            // The keys of a table read from a document by `Reader`: PHP turns a numeric key into an integer whether the
            // cast is written or not. The name `Reader::name()` refuses: `sprintf()` writes a scalar as the string the cast
            // gives.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$map\\[\\(string\\) \\$key\\] = \\$item;',
                    '.*\\? \\(string\\) \\$name : get_debug_type\\(\\$name\\).*',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The engine renders in the Twig sandbox, and the loading reads YAML documents: the library sees the rest of
        // the world.
        'ruleset' => [
            'TilttekGenerator' => ['DefaultAllowed', 'TilttekDice', 'Forbidden'],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // à corriger : complexité cognitive de la classe (137) au-dessus de 100, à découper.
            ['identifier' => 'complexity.classLike', 'path' => 'src/Engine/Producer.php', 'count' => 1],
            // à corriger : SandboxExtension de Twig est marquée interne, à remplacer par l'API publique du bac à sable.
            ['identifier' => 'method.internalClass', 'path' => 'src/Engine/Producer.php', 'count' => 1],
            // à corriger : SandboxExtension de Twig est marquée interne, à remplacer par l'API publique du bac à sable.
            ['identifier' => 'new.internalClass', 'path' => 'src/Engine/Producer.php', 'count' => 1],
            // à corriger : complexité cognitive de Italian::article() (33) au-dessus de 25, à découper.
            ['identifier' => 'complexity.functionLike', 'path' => 'src/Grammar/Italian.php', 'count' => 1],
            // à corriger : complexité cognitive de Spanish::article() (26) au-dessus de 25, à découper.
            ['identifier' => 'complexity.functionLike', 'path' => 'src/Grammar/Spanish.php', 'count' => 1],
            // à corriger : interfaces publiques hors d'un espace de noms Contract, à déplacer.
            ['identifier' => 'symplify.requiredInterfaceContractNamespace', 'paths' => [
                'src/Definition/Body.php',
                'src/Definition/Field.php',
                'src/Grammar/Grammar.php',
                'src/Timer.php',
            ]],
        ],
    ],
];
