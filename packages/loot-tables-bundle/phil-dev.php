<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'docs' => ['public' => true],
    'paths' => ['src', 'tests'],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : interfaces publiques hors d'un espace de noms Contract, à déplacer.
            ['identifier' => 'symplify.requiredInterfaceContractNamespace', 'path' => 'src/Domain/Repository/LootDrawRepository.php'],
        ],
    ],
];
