<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own. `tilttek/dice-bundle` is
 * used by the production code but only required in development and suggested: its adapter is loaded only by an
 * application that rolls with the bundle and declares it as the service of the bundle's port.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['tilttek/dice-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
