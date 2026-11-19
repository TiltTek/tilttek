<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The optional integrations — the
 * form of the physical dice on the Form component, its options resolver and the translator's contracts, the components
 * on Symfony UX, the command that rolls a draw again on the Console component, the production sheet on
 * `phil/paged-media` — are required in development only and suggested: the bundle registers each only when its package
 * is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/paged-media', 'symfony/console', 'symfony/form', 'symfony/options-resolver', 'symfony/translation-contracts', 'symfony/ux-twig-component'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
