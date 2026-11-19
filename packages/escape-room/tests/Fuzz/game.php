<?php

declare(strict_types=1);

/*
 * The fuzz target of a stored game, run by `phil-dev fuzz`: any JSON gives a game of the room that writes back as it
 * was read, or is refused with `InvalidRoom`, never another exception.
 */

use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\Game;
use Tilttek\EscapeRoom\Room;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Hint;
use Tilttek\PuzzleBuilder\Puzzle;

require getenv('PHIL_DEV_AUTOLOAD');

$room = Room::chain('crypt', 'The crypt', [
    new Puzzle('candle', 'The candle', new AnswerKey(['wick']), [new Hint('Look at the top'), new Hint('It burns')]),
    new Puzzle('tomb', 'The tomb', new AnswerKey(['1347'])),
]);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($room): void {
    try {
        $values = json_decode($input, true, 8, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($values)) {
        return;
    }

    try {
        $game = Game::fromArray($room, $values);
    } catch (InvalidRoom) {
        return;
    }

    if (Game::fromArray($room, $game->toArray())->toArray() !== $game->toArray()) {
        throw new LogicException('The game written does not read back the same.');
    }
});
$config->setMaxLen(4096);
