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
                'src/CatalogProvider.php',
                'src/CollectionObserver.php',
                'src/Daily/DailyStore.php',
                'src/Daily/DateRule.php',
                'src/Dice/ChoiceStore.php',
                'src/Pack/OpeningStore.php',
                'src/Pack/SeedSource.php',
                'src/Store/CompletionStore.php',
                'src/Store/CopyStore.php',
                'src/WritableCatalog.php',
            ]],
        ],
    ],
    'docs' => ['public' => true],
];
