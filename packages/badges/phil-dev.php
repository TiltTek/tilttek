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
                'src/AwardObserver.php',
                'src/Criterion/Criterion.php',
                'src/Criterion/Leaf.php',
                'src/Criterion/Reading.php',
                'src/Measures.php',
                'src/ShareChannel.php',
                'src/Store/AwardStore.php',
                'src/Store/GrantStore.php',
                'src/Store/ProfileStore.php',
                'src/Store/ProgressStore.php',
            ]],
        ],
    ],
    'docs' => ['public' => true],
];
