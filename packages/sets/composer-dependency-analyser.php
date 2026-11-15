<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own. The Symfony integration
 * under `src/Symfony` — the command, the message and its handler, the schedule — uses packages required in development
 * only and suggested; the rest of `src` uses none of them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPath(__DIR__.'/src/Symfony', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
