<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this monorepo: the globs of its packages, each of which keeps its own paths and
 * exceptions in its own phil-dev.php, and the terms no package names. See vendor/phil/dev-tools/doc/configuration.md
 * for every section.
 */
return [
    // Every class lives in a package; the root keeps only the bootstrap of the test runs.
    'paths' => ['tests'],
    'packages' => ['packages/*'],

    'deptrac' => [
        // What every library and every domain may use: the default of phil/dev-tools — the Symfony components and
        // contracts, the polyfills and the PSR interfaces — and the Doctrine mapping attributes, which the domain models
        // of the bundles carry; the rest of Doctrine stays outside the domain.
        'default' => [
            '^Symfony\\\\Component\\\\.*',
            '^Symfony\\\\Contracts\\\\.*',
            '^Symfony\\\\Polyfill\\\\.*',
            '^Psr\\\\.*',
            '^Doctrine\\\\ORM\\\\Mapping\\\\.*',
        ],
    ],

    'unused' => [
        // The root holds no code of its own, only the packages it develops: composer-unused counts its `php`
        // requirement as a package nothing uses.
        'ignore' => ['php'],
    ],

    'rector' => [
        // The integrations with phil/privacy-bundle, phil/open-badges-bundle and phil/almanac, suggested rather than
        // required and not published yet: Rector cannot load the types they implement or call, and phpstan.neon leaves
        // the same files out. Each skip goes once those packages can be installed here.
        'skip' => [
            'packages/*/src/Infrastructure/Privacy/*',
            'packages/*/tests/Unit/Infrastructure/*PrivacyTest.php',
            'packages/badges-bundle/src/Infrastructure/OpenBadges/*',
            'packages/collections-bundle/src/Infrastructure/Almanac/*',
            'packages/collections-bundle/tests/Unit/Infrastructure/AlmanacDateRuleTest.php',
            'packages/badges-bundle/tests/Unit/Infrastructure/BadgeCredentialsTest.php',
            'packages/quests-bundle/src/Infrastructure/Almanac/*',
            'packages/quests-bundle/tests/Unit/Infrastructure/AlmanacScheduleTest.php',
        ],
    ],

    'docs' => [
        // The French mirrors catch up with the English documentation: their findings are reported, not blocking.
        'translations' => 'advisory',
    ],
];
