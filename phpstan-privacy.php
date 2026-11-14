<?php

declare(strict_types=1);

/*
 * The integrations of the bundles with phil/privacy-bundle, suggested rather than required. When it is installed, they
 * are analysed like the rest of the code. When it is not, the classes that implement them and their tests are left
 * out, and the errors PHPStan reports where the bundle classes register them and the functional tests reach them are
 * expected: the code checks that the package is installed before it touches it, but PHPStan reads every class name it
 * names.
 */
if (is_file(__DIR__.'/vendor/phil/privacy-bundle/composer.json')) {
    return [];
}

$ignoreErrors = [];

// Each bundle by its package, its class name, and the table and the key its functional test reads from an export.
$bundles = [
    'activity' => ['Activity', null],
    'badges' => ['Badges', 'awards'],
    'collections' => ['Collections', 'copies'],
    'points' => ['Points', 'points'],
    'progression' => ['Progression', 'gains'],
    'quests' => ['Quests', 'contributions'],
];

foreach ($bundles as $package => [$name, $key]) {
    $ignoreErrors[] = ['identifier' => 'argument.type', 'path' => __DIR__."/packages/{$package}-bundle/src/Tilttek{$name}Bundle.php", 'count' => 2];
    $ignoreErrors[] = ['identifier' => 'class.notFound', 'path' => __DIR__."/packages/{$package}-bundle/src/Tilttek{$name}Bundle.php", 'count' => 2];
    // The functional tests of badges-bundle reach phil/open-badges-bundle too, whose errors phpstan.neon expects: the
    // messages tell them apart.
    $ignoreErrors[] = ['identifier' => 'class.notFound', 'message' => '#Phil\\\\PrivacyBundle\\\\#', 'path' => __DIR__."/packages/{$package}-bundle/tests/Functional/{$name}BundleTest.php", 'count' => 5];

    if (null !== $key) {
        $ignoreErrors[] = ['identifier' => 'offsetAccess.nonOffsetAccessible', 'message' => "#^Cannot access offset '(tilttek_{$package}|{$key})' on mixed\\.$#", 'path' => __DIR__."/packages/{$package}-bundle/tests/Functional/{$name}BundleTest.php", 'count' => 2];
    }
}

return [
    'parameters' => [
        'excludePaths' => [
            'analyse' => [
                __DIR__.'/packages/*/src/Infrastructure/Privacy/*',
                __DIR__.'/packages/*/tests/Unit/Infrastructure/*PrivacyTest.php',
            ],
        ],
        'ignoreErrors' => $ignoreErrors,
    ],
];
