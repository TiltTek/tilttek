<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The optional integrations —
 * the schema listener on the ORM, the command on the console, the push to a Mercure hub, the PDF of the cards on
 * `phil/paged-media`, the privacy module on `phil/privacy-bundle` — are required in development only and suggested:
 * the bundle registers each only when its package is installed or configured. `ext-intl` formats the ICU messages of
 * the translation domain, which no PHP file names. `phil/content-warnings-bundle` is reached by the id of its
 * `Catalogue` service, not by a class.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'symfony/console', 'symfony/mercure', 'phil/paged-media', 'phil/privacy-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('phil/content-warnings-bundle', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreErrorsOnExtension('ext-intl', [ErrorType::UNUSED_DEPENDENCY]);
