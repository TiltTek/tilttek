<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'deptrac' => [
        // Composer's runtime API, which every Composer 2.2 install provides through `composer-runtime-api`: the data
        // packages installed are listed by `InstalledDataPackages`.
        'allow' => ['^Composer\\\\InstalledVersions$'],
    ],
    'fuzz' => [
        'targets' => [
            'document' => 'tests/Fuzz/document.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The systems and the records of a system, in `SystemReader`, `Systems::with()` and `GameSystem::localised()`:
            // the constructors take their arguments by key and rebuild the list themselves — a string key in a spread
            // becomes a named argument collected by the variadic, and `byKey()` indexes the records afresh — so the
            // list that is passed on is never read by its keys.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*new Systems\\(\\.\\.\\.array_values\\(\\$systems\\)\\);',
                    '.*new self\\(\\.\\.\\.array_values\\(\\[\\.\\.\\.\\$this->systems.*',
                    '.*array_values\\(\\$(attributes|dice|terms|variants|difficulties|partyRoles)\\),',
                ],
            ],
            // The depth of the YAML dump, in `SystemWriter::toYaml()`: the deepest mapping of a document is three levels
            // down, so no depth from 4 up changes the text.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*Yaml::dump\\(\\$this->toArray\\(\\$system\\), 5, 2,.*',
                    // A size left out of an upstream file: 0 or -1 is refused by `UpstreamFile` with the same message.
                    '.*\\$file->int\\(.bytes.\\) \\?\\? 0,.*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*Yaml::dump\\(\\$this->toArray\\(\\$system\\), 5, 2,.*'],
            ],
            // The budgets of a difficulty, in `ImportProfile::srd52Encounters()`: a level is an integer array key whether
            // it is cast to a string first or not.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\$budgets\\[\\(string\\) \\(\\$index \\+ 1\\)\\] = .*'],
            ],
            // The end of a document path, in `DataPackage::path()`: a trailing line break is refused by the segment
            // check, which has its own `D` flag, so the flag of the extension check changes nothing.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['.*\\(\\?:ya\\?ml\\|json\\).*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config` and its classes.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
