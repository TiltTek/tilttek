<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // phil/almanac and phil/privacy-bundle are optional integrations, suggested rather than required: the
        // infrastructure that implements them and the bundle class that wires them see their layers, as they would
        // see them from require-dev, and the rest of the bundle still does not.
        'ruleset' => [
            'TilttekCollectionsBundleInfrastructure' => [
                'TilttekCollectionsBundleDomain',
                'TilttekCollectionsBundleApplication',
                'DefaultAllowed',
                'Forbidden',
                'PhilResource',
                'TilttekCollections',
                'TilttekActivity',
                'TilttekActivityBundleDomain',
                'TilttekActivityBundleApplication',
                'TilttekActivityBundleInfrastructure',
                'TilttekActivityBundle',
                'TilttekDiceBundleDomain',
                'TilttekDiceBundleApplication',
                'TilttekDiceBundleInfrastructure',
                'TilttekDiceBundle',
                'TilttekExchange',
                'PhilAlmanac',
                'PhilPrivacyBundleDomain',
                'PhilPrivacyBundleApplication',
                'PhilPrivacyBundle',
            ],
            'TilttekCollectionsBundle' => [
                'TilttekCollectionsBundleDomain',
                'TilttekCollectionsBundleApplication',
                'TilttekCollectionsBundleInfrastructure',
                'DefaultAllowed',
                'Forbidden',
                'PhilResource',
                'TilttekCollections',
                'TilttekActivity',
                'TilttekActivityBundleDomain',
                'TilttekActivityBundleApplication',
                'TilttekActivityBundleInfrastructure',
                'TilttekActivityBundle',
                'TilttekDiceBundleDomain',
                'TilttekDiceBundleApplication',
                'TilttekDiceBundleInfrastructure',
                'TilttekDiceBundle',
                'TilttekExchange',
                'PhilAlmanac',
                'PhilPrivacyBundleDomain',
                'PhilPrivacyBundleApplication',
                'PhilPrivacyBundle',
            ],
        ],
    ],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : new Schema() est interne à DBAL, les tests de schéma doivent passer par un SchemaManager ou une SchemaConfig.
            ['identifier' => 'method.internal', 'path' => 'tests/Unit/Infrastructure/Database.php', 'count' => 1],
            // à corriger : new Schema() est interne à DBAL, les tests de schéma doivent passer par un SchemaManager ou une SchemaConfig.
            ['identifier' => 'method.internal', 'path' => 'tests/Unit/Infrastructure/TablesTest.php', 'count' => 4],
            // à corriger : API de schéma DBAL dépréciée dans les tests (getName(), getType()).
            ['identifier' => 'method.deprecated', 'path' => 'tests/Unit/Infrastructure/TablesTest.php', 'count' => 2],
            // à corriger : getName()/getObjectName() appelées sur des classes internes de DBAL dans les tests.
            ['identifier' => 'method.internalClass', 'path' => 'tests/Unit/Infrastructure/TablesTest.php', 'count' => 1],
            // The closure of a configuration rule receives the node as an array whose values the installed release of
            // symfony/config leaves `mixed`.
            ['identifier' => 'booleanAnd.leftNotBoolean', 'path' => 'src/TilttekCollectionsBundle.php', 'count' => 1],
        ],
    ],
    'docs' => ['public' => true],
];
