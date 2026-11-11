<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : interfaces publiques hors d'un espace de noms Contract, à déplacer.
            ['identifier' => 'symplify.requiredInterfaceContractNamespace', 'paths' => [
                'src/Catalog.php',
                'src/QuestObserver.php',
                'src/Schedule/Schedule.php',
                'src/Schedule/ScheduleReader.php',
                'src/Store/CompletionStore.php',
                'src/Store/ContributionStore.php',
                'src/Store/DuelStore.php',
                'src/Store/EnrolmentStore.php',
                'src/WritableCatalog.php',
            ]],
        ],
    ],
    'docs' => ['public' => true],
];
