<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The optional integrations —
 * the schema listener on the ORM, the commands on the console, the Twig functions, the translation and the identifiers
 * of the credentials — are required in development only and suggested: the bundle registers each only when its
 * package is installed.
 *
 * `phil/open-badges-bundle`, with `phil/open-badges`, and `phil/privacy-bundle` are optional integrations, only
 * suggested: the bundle registers the credentials and the privacy module only when they are there, and their tests,
 * like their bundles in the test kernel, are skipped when they are not. Their classes are unknown to the bundle
 * installed on its own.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'symfony/console', 'twig/twig', 'symfony/translation-contracts', 'symfony/uid'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownClassesRegex('~^Phil\\\\(OpenBadges|OpenBadgesBundle|PrivacyBundle)\\\\~');
