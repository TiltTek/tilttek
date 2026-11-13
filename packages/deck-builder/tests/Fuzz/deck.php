<?php

declare(strict_types=1);

/*
 * The fuzz target of a deck, run by `phil-dev fuzz`: any text reads as a deck that writes back to the same deck, or is
 * refused with `InvalidDeck`, never another exception.
 */

use Tilttek\DeckBuilder\Catalog\OwnedCardList;
use Tilttek\DeckBuilder\Deck;
use Tilttek\DeckBuilder\DeckDiff;
use Tilttek\DeckBuilder\Exception\InvalidDeck;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $deck = Deck::fromJson($input);
    } catch (InvalidDeck) {
        return;
    }

    if (!Deck::fromJson($deck->toJson())->equals($deck)) {
        throw new LogicException('A deck does not read back as itself.');
    }

    if (!DeckDiff::between($deck, $deck)->isEmpty() || $deck->limitedTo(new OwnedCardList([]))->size() > 0) {
        throw new LogicException('A deck differs from itself, or keeps cards a collection does not own.');
    }
});
$config->setMaxLen(4096);
