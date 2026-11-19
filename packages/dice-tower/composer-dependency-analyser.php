<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. Twig, the router and Messenger
 * are used by the production code but only required in development and suggested: the bundle registers the overlay
 * only when Twig is there, generates the route of the entries only when a router is, and reaches the relays through a
 * bus only when one is named.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['twig/twig', 'symfony/routing', 'symfony/messenger'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
