<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    // The optional bridges, under src/Bridge, used only when the application installs the package they bridge to.
    ->ignoreErrorsOnPackages(['tilttek/quests', 'tilttek/collections'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
