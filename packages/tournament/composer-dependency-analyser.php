<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own. `tilttek/rating` is used by
 * `Seeding\RatingSeeding` alone, and only required in development and suggested: an application that seeds by ratings
 * installs it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('tilttek/rating', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
