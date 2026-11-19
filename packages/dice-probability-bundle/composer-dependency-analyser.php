<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. Twig, Symfony UX, HttpFoundation
 * and the rate limiter are used by the production code but only required in development and suggested: the bundle
 * registers the Twig extension and the Live Component, with its rate limit, only when their packages are there, and
 * reduces the long fractions with ext-gmp only when it is loaded. `tilttek/dice-bundle` is named by no class: its
 * `DiceRoller`, custom dice and limits are the services the bundle's services are given, by their identifiers.
 */
$configuration = new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['twig/twig', 'symfony/ux-live-component', 'symfony/http-foundation', 'symfony/rate-limiter'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('tilttek/dice-bundle', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);

// ext-gmp is suggested: its functions are seen as its own only where it is loaded.
return extension_loaded('gmp') ? $configuration->ignoreErrorsOnExtension('ext-gmp', [ErrorType::SHADOW_DEPENDENCY]) : $configuration;
