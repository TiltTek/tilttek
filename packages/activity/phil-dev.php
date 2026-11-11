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
                'src/JournalObserver.php',
                'src/Log/ActivityLog.php',
                'src/Projection/Checkpoints.php',
                'src/Projection/Projection.php',
            ]],
        ],
    ],
    'docs' => ['public' => true],
];
