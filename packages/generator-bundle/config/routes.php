<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tilttek\GeneratorBundle\Infrastructure\Http\DrawController;

/*
 * The draw API, imported by the application under a prefix; it answers 404 until `tilttek_generator.api.enabled`:
 *
 *     # config/routes/tilttek_generator.yaml
 *     tilttek_generator:
 *         resource: '@TilttekGeneratorBundle/config/routes.php'
 *         prefix: /api/generators
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('tilttek_generator_draw', '/{generator}')
        ->controller(DrawController::class)
        ->requirements(['generator' => '[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)*'])
        ->methods(['GET']);
};
