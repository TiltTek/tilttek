<?php

declare(strict_types=1);

/*
 * The fuzz target of the gestures read back from a store or a published game, run by `phil-dev fuzz`: any JSON is
 * either refused with the library's own exceptions or gives a gesture that writes itself back to the same array.
 */

use Tilttek\CardDeck\Exception\CardDeckException;
use Tilttek\CardDeck\Gesture\GestureCodec;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $data = json_decode($input, true, 8, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    try {
        $gesture = GestureCodec::fromArray($data);
    } catch (CardDeckException) {
        // The refusals the codec promises for data it cannot read.
        return;
    }

    if (GestureCodec::fromArray($gesture->toArray()) !== $gesture) {
        throw new LogicException('A gesture does not read back to itself.');
    }
});
$config->setMaxLen(512);
