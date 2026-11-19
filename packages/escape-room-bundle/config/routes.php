<?php

declare(strict_types=1);

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ConsentController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ConsoleController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\LeaderboardController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ScreenController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\Screens;

/*
 * The routes of the console, the hint screen, the consent and the leaderboard:
 *
 *     # config/routes/tilttek_escape_room.yaml
 *     tilttek_escape_room:
 *         resource: '@TilttekEscapeRoomBundle/config/routes.php'
 */
return static function (RoutingConfigurator $routes): void {
    $game = ['game' => Screens::GAME];

    $routes->add(ConsoleController::CONSOLE, '/escape-room/console/{game}')
        ->controller([ConsoleController::class, 'show'])->methods(['GET'])->requirements($game);
    $routes->add(ConsoleController::ACT, '/escape-room/console/{game}/{action}')
        ->controller([ConsoleController::class, 'act'])->methods(['POST'])->requirements([...$game, 'action' => implode('|', ConsoleController::ACTIONS)]);
    $routes->add(ScreenController::SCREEN, '/escape-room/screen/{game}')
        ->controller([ScreenController::class, 'show'])->methods(['GET'])->requirements($game);
    $routes->add(ScreenController::STATE, '/escape-room/screen/{game}/state')
        ->controller([ScreenController::class, 'state'])->methods(['GET'])->requirements($game);
    $routes->add(ConsentController::CONSENT, '/escape-room/games/{game}/consent')
        ->controller(ConsentController::class)->methods(['POST'])->requirements($game);
    $routes->add(LeaderboardController::LEADERBOARD, '/escape-room/leaderboard/{room}')
        ->controller(LeaderboardController::class)->methods(['GET'])->requirements(['room' => Screens::ROOM]);
};
