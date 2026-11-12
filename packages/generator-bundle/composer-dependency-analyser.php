<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The optional integrations —
 * the controller of the API, the commands, the rate limits, the cache and the lock of the states, the Twig function
 * and the Stimulus controller — are required in development only and suggested: the bundle registers each only when its
 * component is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/console', 'symfony/http-foundation', 'symfony/rate-limiter', 'symfony/lock', 'symfony/asset-mapper', 'psr/cache', 'psr/clock', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
