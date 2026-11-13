<?php

declare(strict_types=1);

/*
 * The fuzz target of a format, run by `phil-dev fuzz`: any text reads as a format that writes back to the same format,
 * or is refused with `InvalidDeck`, never another exception.
 */

use Tilttek\DeckBuilder\Exception\InvalidDeck;
use Tilttek\DeckBuilder\Format;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $format = Format::fromJson($input);
    } catch (InvalidDeck) {
        return;
    }

    if (!Format::fromJson($format->toJson())->equals($format)) {
        throw new LogicException('A format does not read back as itself.');
    }

    $previous = null;

    foreach ($format->tabooLists as $list) {
        if (null !== $previous && strcmp($previous, $list->effectiveFrom->format('Y-m-d')) >= 0) {
            throw new LogicException('The taboo lists are not in the order of their days.');
        }

        $previous = $list->effectiveFrom->format('Y-m-d');
    }
});
$config->setMaxLen(4096);
