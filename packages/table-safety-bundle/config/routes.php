<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tilttek\TableSafetyBundle\Infrastructure\Http\AnswersController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\CardsController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\GameMasterController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\Screens;
use Tilttek\TableSafetyBundle\Infrastructure\Http\TableController;

/*
 * The routes of the screens and the signals:
 *
 *     # config/routes/tilttek_table_safety.yaml
 *     tilttek_table_safety:
 *         resource: '@TilttekTableSafetyBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $table = ['table' => Screens::TABLE];

    $routes->add(TableController::SCREEN, '/table-safety/{table}')
        ->controller([TableController::class, 'screen'])->methods(['GET'])->requirements($table);
    $routes->add(TableController::RAISE, '/table-safety/{table}/signals')
        ->controller([TableController::class, 'raise'])->methods(['POST'])->requirements($table);
    $routes->add(TableController::FEED, '/table-safety/{table}/signals')
        ->controller([TableController::class, 'feed'])->methods(['GET'])->requirements($table);
    $routes->add(TableController::ACKNOWLEDGE, '/table-safety/{table}/signals/{id}/acknowledge')
        ->controller([TableController::class, 'acknowledge'])->methods(['POST'])->requirements([...$table, 'id' => '[0-9a-f]{32}']);
    $routes->add(AnswersController::ANSWERS, '/table-safety/{table}/answers')
        ->controller([AnswersController::class, 'answers'])->methods(['GET', 'POST'])->requirements($table);
    $routes->add(AnswersController::SESSION_ZERO, '/table-safety/{table}/session-zero')
        ->controller([AnswersController::class, 'sessionZero'])->methods(['GET', 'POST'])->requirements($table);
    $routes->add(AnswersController::FEEDBACK, '/table-safety/{table}/feedback/{session}')
        ->controller([AnswersController::class, 'feedback'])->methods(['GET', 'POST'])->requirements([...$table, 'session' => '[a-z0-9]+(?:-[a-z0-9]+)*']);
    $routes->add(CardsController::CARDS, '/table-safety/{table}/cards')
        ->controller([CardsController::class, 'cards'])->methods(['GET'])->requirements($table);
    $routes->add(CardsController::PDF, '/table-safety/{table}/cards.pdf')
        ->controller([CardsController::class, 'pdf'])->methods(['GET'])->requirements($table);
    $routes->add(GameMasterController::SCREEN, '/table-safety/{table}/game-master')
        ->controller(GameMasterController::class)->methods(['GET', 'POST'])->requirements($table);
};
