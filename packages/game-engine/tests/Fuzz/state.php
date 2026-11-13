<?php

declare(strict_types=1);

/*
 * The fuzz target of the states a cache gives back, run by `phil-dev fuzz`: any JSON text is either refused with the
 * library's own exceptions or gives a state that, written and read back, is the same state.
 */

use Tilttek\GameEngine\Exception\GameEngineException;
use Tilttek\GameEngine\GameState;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $data = json_decode($input, true, 16);

    if (!is_array($data)) {
        return;
    }

    try {
        $state = GameState::fromArray($data);
    } catch (GameEngineException) {
        // The refusals the state promises.
        return;
    }

    if (GameState::fromArray($state->toArray())->toArray() !== $state->toArray()) {
        throw new LogicException('A state written and read back is another state.');
    }
});
$config->setMaxLen(2048);
