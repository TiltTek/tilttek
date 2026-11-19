<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The optional integrations —
 * the calendars edited through the ORM and the schema listener, the commands on the console, the privacy module on
 * `phil/privacy-bundle` — are required in development only and suggested: the bundle registers each only when its package is installed.
 * `ext-intl` formats the ICU messages of the translation domain, which no PHP file names. The test kernel calls
 * `service()`, a function the dependency-injection component declares beside `ContainerConfigurator`, outside its
 * autoloading.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'symfony/console', 'phil/privacy-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnExtension('ext-intl', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreUnknownFunctions(['Symfony\\Component\\DependencyInjection\\Loader\\Configurator\\service']);
