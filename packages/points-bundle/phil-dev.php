<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // phil/privacy-bundle is an optional integration, suggested rather than required: the infrastructure that
        // implements it and the bundle class that wires it see its layers, as they would see them from require-dev,
        // and the rest of the bundle still does not.
        'ruleset' => [
            'TilttekPointsBundleInfrastructure' => [
                'TilttekPointsBundleDomain',
                'TilttekPointsBundleApplication',
                'DefaultAllowed',
                'Forbidden',
                'PhilResource',
                'TilttekPoints',
                'PhilPrivacyBundleDomain',
                'PhilPrivacyBundleApplication',
                'PhilPrivacyBundleInfrastructure',
                'PhilPrivacyBundle',
                'TilttekActivity',
                'TilttekActivityBundleDomain',
                'TilttekActivityBundleApplication',
                'TilttekActivityBundleInfrastructure',
                'TilttekActivityBundle',
            ],
            'TilttekPointsBundle' => [
                'TilttekPointsBundleDomain',
                'TilttekPointsBundleApplication',
                'TilttekPointsBundleInfrastructure',
                'DefaultAllowed',
                'Forbidden',
                'PhilResource',
                'TilttekPoints',
                'PhilPrivacyBundleDomain',
                'PhilPrivacyBundleApplication',
                'PhilPrivacyBundleInfrastructure',
                'PhilPrivacyBundle',
                'TilttekActivity',
                'TilttekActivityBundleDomain',
                'TilttekActivityBundleApplication',
                'TilttekActivityBundleInfrastructure',
                'TilttekActivityBundle',
            ],
        ],
    ],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : new Schema() est interne à DBAL, les tests de schéma doivent passer par un SchemaManager ou une SchemaConfig.
            ['identifier' => 'method.internal', 'path' => 'tests/Unit/Infrastructure/Database.php', 'count' => 1],
            // à corriger : API de schéma DBAL dépréciée dans les tests (getColumns(), getName(), getPrimaryKey(), isUnique()).
            ['identifier' => 'method.deprecated', 'path' => 'tests/Unit/Infrastructure/DbalLedgerTest.php', 'count' => 12],
            // à corriger : getName()/getObjectName() appelées sur des classes internes de DBAL dans les tests.
            ['identifier' => 'method.internalClass', 'path' => 'tests/Unit/Infrastructure/DbalLedgerTest.php', 'count' => 2],
            // à corriger : new Schema() est interne à DBAL, les tests de schéma doivent passer par un SchemaManager ou une SchemaConfig.
            ['identifier' => 'method.internal', 'path' => 'tests/Unit/Infrastructure/DbalLedgerTest.php', 'count' => 1],
            // à corriger : new Schema() est interne à DBAL, les tests de schéma doivent passer par un SchemaManager ou une SchemaConfig.
            ['identifier' => 'method.internal', 'path' => 'tests/Unit/Infrastructure/PointsSchemaListenerTest.php', 'count' => 2],
        ],
    ],
    'docs' => ['public' => true],
];
