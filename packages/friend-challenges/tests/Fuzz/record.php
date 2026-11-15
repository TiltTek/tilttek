<?php

declare(strict_types=1);

/*
 * The fuzz target of a challenge read back from storage, run by `phil-dev fuzz`: any JSON text gives a challenge that
 * writes back as it was read, or is refused with `InvalidChallenge`, never another exception.
 */

use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $record = json_decode($input, true, 16, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($record)) {
        return;
    }

    try {
        $challenge = Challenge::fromArray($record);
    } catch (InvalidChallenge) {
        return;
    }

    if (Challenge::fromArray($challenge->toArray())->toArray() !== $challenge->toArray()) {
        throw new LogicException('The challenge written does not read back the same.');
    }
});
$config->setMaxLen(8192);
