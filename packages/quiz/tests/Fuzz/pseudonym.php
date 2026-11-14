<?php

declare(strict_types=1);

/*
 * The fuzz target of a guest's pseudonym, run by `phil-dev fuzz`: any input is either refused with the library's own
 * exception or gives a guest whose pseudonym the library's word list answers for without error, the same way twice.
 */
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Player;
use Tilttek\Quiz\Pseudonym\WordListFilter;

require getenv('PHIL_DEV_AUTOLOAD');

$filter = WordListFilter::defaults();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($filter): void {
    if (!$filter->allows($input) && $filter->allows($input)) {
        throw new LogicException('The word list answers two ways for the same text.');
    }

    try {
        $guest = Player::guest('guest-1', $input);
    } catch (InvalidQuiz) {
        // The refusal the player promises.
        return;
    }

    if (null === $guest->pseudonym || $filter->allows($guest->pseudonym) !== $filter->allows($guest->pseudonym)) {
        throw new LogicException('A guest kept no pseudonym, or the word list answers two ways for it.');
    }
});
