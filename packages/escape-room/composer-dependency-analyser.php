<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own. `phil/reservation` is
 * required in development only and suggested: `Reservation\BookableSessionReservations` and `Reservation\Contract\RoomBookables`
 * are used only by an application that installs it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/reservation'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
