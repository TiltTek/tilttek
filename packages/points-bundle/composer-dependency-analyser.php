<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The optional integrations —
 * the schema listener on the ORM, the commands on the console — are required in development only and suggested: the
 * bundle registers each only when its package is installed.
 *
 * `phil/privacy-bundle` is an optional integration, only suggested: the bundle registers the privacy module only when
 * it is there, and its tests, like the privacy bundle in the test kernel, are skipped when it is not. Its classes are
 * unknown to the bundle installed on its own.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'symfony/console'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownClassesRegex('~^Phil\\\\PrivacyBundle\\\\~');
