<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 *
 * The `Normalizer` class comes from intl when the extension is loaded, and from symfony/polyfill-intl-normalizer
 * otherwise: the analyser sees the extension's class, so it reports intl, which is only suggested, as a shadow
 * dependency and the polyfill as unused.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnExtension('ext-intl', [ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPackage('symfony/polyfill-intl-normalizer', [ErrorType::UNUSED_DEPENDENCY]);
