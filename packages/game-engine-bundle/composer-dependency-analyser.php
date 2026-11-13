<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The optional integrations — the
 * state cache, the lock, the Mercure publisher, the endpoints, the commands, the schema listener of the ORM, the Twig
 * functions and the Stimulus controller — are required in development only and suggested: the bundle registers each
 * only when its component is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'psr/cache', 'psr/log', 'symfony/asset-mapper', 'symfony/console', 'symfony/http-foundation', 'symfony/lock', 'symfony/mercure', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
