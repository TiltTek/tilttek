<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The command, the handler and
 * the schedule are optional integrations, on packages required in development only and suggested: the bundle registers
 * each only when its package is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/console', 'symfony/lock', 'symfony/messenger', 'symfony/scheduler'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
