<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tilttek\GameEngineBundle\Infrastructure\Http\TableController;

/*
 * The endpoints of the views and of the logs of finished tables, imported by the application under a prefix:
 *
 *     # config/routes/tilttek_game_engine.yaml
 *     tilttek_game_engine:
 *         resource: '@TilttekGameEngineBundle/config/routes.php'
 *         prefix: /game-tables
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('tilttek_game_engine_view', '/{table}')
        ->controller([TableController::class, 'view'])
        ->requirements(['table' => '[A-Za-z][A-Za-z0-9:._-]{0,127}'])
        ->methods(['GET']);
    $routes->add('tilttek_game_engine_log', '/{table}/log')
        ->controller([TableController::class, 'log'])
        ->requirements(['table' => '[A-Za-z][A-Za-z0-9:._-]{0,127}'])
        ->methods(['GET']);
};
