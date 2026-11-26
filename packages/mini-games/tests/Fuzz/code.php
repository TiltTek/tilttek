<?php

declare(strict_types=1);

/*
 * The fuzz target of the guesses of Bulls and Cows, run by `phil-dev fuzz`: any text is either refused with the
 * library's own exception or read as a code of different digits that is the text itself.
 */

use Tilttek\MiniGames\Exception\InvalidMiniGame;
use Tilttek\MiniGames\Game\Code;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    foreach ([3, 4, 5] as $length) {
        try {
            $code = Code::read($input, $length);
        } catch (InvalidMiniGame) {
            // The refusal the reader promises for a text that is not a guess.
            continue;
        }

        if ($code->digits !== $input || count(array_unique(str_split($input))) !== $length || 1 !== preg_match('/^[0-9]+$/D', $input)) {
            throw new LogicException('A guess is read as something other than its different digits.');
        }

        [$bulls, $cows] = $code->score($code);

        if ($length !== $bulls || 0 !== $cows) {
            throw new LogicException('A code does not score all bulls against itself.');
        }
    }
});
$config->setMaxLen(16);
