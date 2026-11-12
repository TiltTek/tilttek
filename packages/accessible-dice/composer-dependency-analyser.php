<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. Twig is used by the production
 * code but only required in development and suggested: the bundle registers the form only when Twig is there.
 * `tilttek/dice-bundle` is named by no class: its custom dice and limits are the services the bundle's services are
 * given, by their identifiers.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('twig/twig', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('tilttek/dice-bundle', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
