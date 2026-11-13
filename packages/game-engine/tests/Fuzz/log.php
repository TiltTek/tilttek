<?php

declare(strict_types=1);

/*
 * The fuzz target of the logs, run by `phil-dev fuzz`: any JSON text is either refused with the library's own
 * exceptions — not a log, a log of another game, a move that no longer applies — or gives a log that replays to a state,
 * whose log written and read back replays to the same state.
 */

use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\Exception\GameEngineException;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\Scene\RoleplayingScene;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $data = json_decode($input, true, 16);

    if (!is_array($data)) {
        return;
    }

    $scene = new RoleplayingScene();

    try {
        $log = GameLog::fromArray($data);
        $state = new Engine()->replay($scene, $log);
    } catch (GameEngineException) {
        // The refusals the log and the engine promise.
        return;
    }

    if ($state !== new Engine()->replay($scene, GameLog::fromArray($log->toArray()))) {
        throw new LogicException('A log written and read back replays to another state.');
    }
});
$config->setMaxLen(2048);
