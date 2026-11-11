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
                'src/Announcer.php',
                'src/Event/ExchangeEvent.php',
                'src/MemberSince.php',
                'src/Reservation/ReservationStore.php',
                'src/Reward/Catalog.php',
                'src/Reward/Requirements.php',
                'src/Reward/WritableCatalog.php',
                'src/Trade/Inventory.php',
                'src/Trade/OfferStore.php',
                'src/Transaction.php',
                'src/Transfer/TransferLog.php',
            ]],
            // à corriger : constructeur privé d'une exception à constructeurs nommés, plus étroit que celui du parent.
            ['identifier' => 'symplify.parentMethodVisibilityOverride', 'path' => 'src/Exception/ExchangeRefused.php'],
        ],
    ],
    'docs' => ['public' => true],
];
