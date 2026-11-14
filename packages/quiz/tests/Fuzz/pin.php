<?php

declare(strict_types=1);

/*
 * The fuzz target of the PIN a player types, run by `phil-dev fuzz`: any input is either refused with the library's
 * own exception or gives six ASCII digits, which read again give the same PIN.
 */
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Pin;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $pin = Pin::parse($input);
    } catch (InvalidQuiz) {
        // The refusal the parser promises.
        return;
    }

    if (1 !== preg_match('/^[0-9]{6}$/D', $pin) || Pin::parse($pin) !== $pin) {
        throw new LogicException('A PIN read is not six ASCII digits that read again the same.');
    }
});
