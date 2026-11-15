<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // phil/open-badges-bundle and phil/privacy-bundle are optional integrations, suggested rather than required:
        // the infrastructure that implements them and the bundle class that wires them see their layers, as they would
        // see them from require-dev, and the rest of the bundle still does not.
        'ruleset' => [
            'TilttekBadgesBundleInfrastructure' => [
                'TilttekBadgesBundleDomain',
                'TilttekBadgesBundleApplication',
                'DefaultAllowed',
                'Forbidden',
                'PhilResource',
                'TilttekBadges',
                'PhilOpenBadges',
                'PhilOpenBadgesBundleDomain',
                'PhilOpenBadgesBundleApplication',
                'PhilOpenBadgesBundleInfrastructure',
                'PhilOpenBadgesBundle',
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
            'TilttekBadgesBundle' => [
                'TilttekBadgesBundleDomain',
                'TilttekBadgesBundleApplication',
                'TilttekBadgesBundleInfrastructure',
                'DefaultAllowed',
                'Forbidden',
                'PhilResource',
                'TilttekBadges',
                'PhilOpenBadges',
                'PhilOpenBadgesBundleDomain',
                'PhilOpenBadgesBundleApplication',
                'PhilOpenBadgesBundleInfrastructure',
                'PhilOpenBadgesBundle',
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
    // The mutants no test can tell from the original, each with its reason.
    'infection' => [
        'mutators' => [
            // The award with the highest number in `BadgeCredentials::latest()`: the stores refuse a second award bearing
            // the same number for a badge and a member, so no two awards compared there share a number and `>=` is worth `>`.
            'GreaterThan' => ['ignoreSourceCodeByRegex' => ['.*\\$award->number > \\$latest->number.*']],
        ],
    ],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : new Schema() est interne à DBAL, les tests de schéma doivent passer par un SchemaManager ou une SchemaConfig.
            ['identifier' => 'method.internal', 'path' => 'tests/Unit/Infrastructure/Database.php', 'count' => 1],
            // à corriger : new Schema() est interne à DBAL, les tests de schéma doivent passer par un SchemaManager ou une SchemaConfig.
            ['identifier' => 'method.internal', 'path' => 'tests/Unit/Infrastructure/TablesTest.php', 'count' => 4],
            // à corriger : API de schéma DBAL dépréciée dans les tests (getName()).
            ['identifier' => 'method.deprecated', 'path' => 'tests/Unit/Infrastructure/TablesTest.php', 'count' => 4],
            // à corriger : getName()/getObjectName() appelées sur des classes internes de DBAL dans les tests.
            ['identifier' => 'method.internalClass', 'path' => 'tests/Unit/Infrastructure/TablesTest.php', 'count' => 4],
        ],
    ],
    'docs' => ['public' => true],
];
