<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The unit past the last one, in `StreakCalculator::status()`, once no active unit is left to jump to: it only
            // has to lie after `$end` for the loop to stop, and nothing reads it after; `$end + 2` stops it the same.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$unit = \\$next < \\$count \\? \\$active\\[\\$next\\] : \\$end \\+ 1;',
                ],
            ],
        ],
    ],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : interfaces publiques hors d'un espace de noms Contract, à déplacer.
            ['identifier' => 'symplify.requiredInterfaceContractNamespace', 'paths' => [
                'src/ProgressionObserver.php',
                'src/Season/SeasonSchedule.php',
                'src/Store/ExperienceStore.php',
                'src/Store/MilestoneStore.php',
                'src/Store/StreakStore.php',
                'src/Streak/TimezoneResolver.php',
            ]],
        ],
    ],
    'docs' => ['public' => true],
];
