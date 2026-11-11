<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The optional integrations —
 * the form type, the constraint, the Twig extension and its translator — are required in development only and
 * suggested: the bundle registers each only when its component is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/form', 'symfony/options-resolver', 'symfony/validator', 'symfony/translation-contracts', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
