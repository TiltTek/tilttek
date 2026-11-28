<?php

declare(strict_types=1);

/*
 * The fuzz target of the moves of Crazy Eights, run by `phil-dev fuzz`: any JSON payload of a play, a draw, a pass or a
 * timeout is either refused with the engine's own exceptions, or played, after which every card is still in exactly
 * one zone and the view of the seat holds no card of another hand.
 */

use Tilttek\GameEngine\Exception\GameEngineException;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\TavernGames\Game\CrazyEights;
use Tilttek\TavernGames\TavernGames;

require getenv('PHIL_DEV_AUTOLOAD');

$games = new TavernGames(new CrazyEights());

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($games): void {
    try {
        $payload = json_decode($input, true, 8, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($payload)) {
        return;
    }

    $tables = new Tables($games->games(), new InMemoryLogStore());
    $tables->open('crazy-eights', 'fuzz', ['ann', 'bob'], [], str_repeat('ab', 32));

    $type = ['crazy-eights.play', 'crazy-eights.draw', 'crazy-eights.pass', 'tavern.timeout'][crc32($input) % 4];

    try {
        $state = $tables->play('fuzz', new Move($type, 'tavern.timeout' === $type ? null : 'ann', array_filter($payload, is_string(...), \ARRAY_FILTER_USE_KEY)));
    } catch (GameEngineException) {
        // The refusals the engine promises for a move the rules or the payload do not allow.
        return;
    }

    $view = $tables->view('fuzz', 'bob');
    $hands = $view['hands'] ?? null;
    $hand = $view['hand'] ?? null;

    if (!is_array($hands) || !is_array($hand) || array_sum($hands) + (int) ($view['draw'] ?? 0) > 52 || count($hand) !== ($hands['bob'] ?? -1) || 1 !== $state->sequence) {
        throw new LogicException('A move of Crazy Eights lost a card or showed another hand.');
    }
});
$config->setMaxLen(256);
