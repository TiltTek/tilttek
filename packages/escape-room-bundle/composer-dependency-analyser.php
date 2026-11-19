<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The optional integrations —
 * the schema listener on the ORM, the command on the console, the schedule on Symfony Scheduler, the reservations on
 * `phil/reservation`, the hub on Symfony Mercure, the limit of the guesses on Symfony Rate Limiter, the privacy module
 * on `phil/privacy-bundle` — are required in development only and suggested: the bundle registers each only when its
 * package is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'symfony/console', 'symfony/scheduler', 'phil/reservation', 'symfony/mercure', 'symfony/rate-limiter', 'phil/privacy-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
