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
            'perchance' => 'tests/Fuzz/perchance.php',
            'tracery' => 'tests/Fuzz/tracery.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The clock in milliseconds: dividing the nanoseconds by one million plus or minus one changes the scale by
            // a millionth, which no test can measure on a time limit. The megabyte of the memory a result may grow by, one
            // more or less: the memory is measured in bytes, and no template stops on a byte. The count of a reference
            // that has no method, which nothing reads. The memory of the draws: `Producer::pool()` finds
            // the entries of a list already filtered by the tags a draw asks for; comparing another field of the memory
            // never finds anything, and the entries are filtered again, the same: only the cost changes.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*memory_get_usage\\(\\) - \\$this->memory > \\$this->limits->megabytes \\* 1_048_576.*',
                    '.*public int \\$count = 1\\).*',
                    'return hrtime\\(true\\) / 1_000_000;',
                    '.*\\$pool = array_find\\(\\$this->pools\\[\\$list->name\\] \\?\\? \\[\\], static fn \\(array \\$pool\\): bool => \\$pool\\[0\\] === \\$tags\\);',
                ],
            ],
            // See `IncrementInteger`: the clock in milliseconds, the megabyte, the count of a reference.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*memory_get_usage\\(\\) - \\$this->memory > \\$this->limits->megabytes \\* 1_048_576.*',
                    '.*public int \\$count = 1\\).*',
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
            // its longest length may end with, filtered by `array_intersect_key()`, which reads only the keys. And the
            // sets of the importers: the symbols that become generators, the generators a symbol draws, the variables an
            // action sets or a generator receives, all read by `isset()`, `array_keys()` or `array_diff_key()`.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$generators\\[\\(string\\) \\$(name|symbol)\\] = true;.*',
                    '.*\\$callers\\[\\$callee\\]\\[\\$symbol\\] = true;.*',
                    '.*\\$this->variables\\[\\$action->name\\] = true;.*',
                    '.*\\$local\\[\\$action->name\\] = true;.*',
                    '.*\\$this->free\\[\\$symbol\\]\\[\\$segment->symbol\\] = true;.*',
                    '\\$known\\[\\$text\\] = true;',
                    '\\$seen\\[\\$key\\] = true;',
                    '\\$taken\\[\\$key\\] = true;',
                    '.*array_intersect_key\\(\\$followers, \\[self::END => true\\]\\);',
                ],
            ],
            // The state a result leaves, in the order of its definition: every value is given or kept by
            // `Producer::advance()`, so the values the order is taken from are all replaced, empty texts or not. And the
            // names of the variables a symbol receives, keys that PHP turned into integers when they are digits, which
            // the callers write as the strings their typed parameters take.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$this->free.*',
                    'return array_replace\\(array_map\\(static fn \\(\\): string => \'\', \\$definition->state\\), \\$current\\);',
                ],
            ],
            // The memory a result has grown by, measured in bytes at each step: no template makes it exactly the limit.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*memory_get_usage\\(\\) - \\$this->memory > \\$this->limits->megabytes \\* 1_048_576.*',
                ],
            ],
            // `D` keeps `$` from matching before a line break at the end of the text, which the texts these patterns
            // read never hold: a line of the tables, trimmed, and the parts of a line.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\(\\?.!.*\\$text, \\$match, \\\\PREG_OFFSET_CAPTURE.*',
                    '.*1 !== preg_match\\(.*, \\$weight\\).*',
                    '.*selectUnique\\|selectMany.*',
                    '.*\\(\\?.max..*',
                ],
            ],
            // A numeric string multiplies and compares as the float it casts to: a weight the pattern has read.
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\(float\\) \\$weight.*',
                ],
            ],
            // `isset()` is true or false, and PHP subtracts either as 1 or 0.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\(int\\) isset\\(\\$sections.*',
                ],
            ],
            // The closing bracket and the colon of an action are two different characters, never at the same place.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$close < \\$colon.*',
                ],
            ],
            // The key of a credit is its three texts: an attribution that finds its key taken is the same one.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\] \\?\\?= \\$attribution;.*',
                ],
            ],
            // The keys of a table read from a document by `Reader`, and of the sections and symbols of the importers: PHP
            // turns a numeric key into an integer whether the cast is written or not. The name `Reader::name()` refuses:
            // `sprintf()` writes a scalar as the string the cast gives. The name of a section, which `preg_replace()`
            // gives as a string unless the pattern fails.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$name = \\(string\\) preg_replace.*',
                    '.*\\$name = \\(string\\) \\$name;.*',
                    '.*\\$generators\\[\\(string\\) \\$(name|symbol)\\] = true;.*',
                    '.*\\$symbol = \\(string\\) array_key_first.*',
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
