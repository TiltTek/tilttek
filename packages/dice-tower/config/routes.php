<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tilttek\DiceTower\Infrastructure\Http\TowerEntriesController;

/*
 * The route of the entries of a table's tower, which the overlay reads what a viewer missed from:
 *
 *     # config/routes/tilttek_dice_tower.yaml
 *     tilttek_dice_tower:
 *         resource: '@TilttekDiceTowerBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add(TowerEntriesController::ROUTE, '/dice-tower/{table}/entries')
        ->controller(TowerEntriesController::class)
        ->methods(['GET'])
        ->requirements(['table' => TowerEntriesController::TABLE]);
};
