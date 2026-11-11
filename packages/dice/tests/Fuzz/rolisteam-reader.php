<?php

declare(strict_types=1);

/*
 * The fuzz target of the reader of Rolisteam's DiceParser notation, run by `phil-dev fuzz`: any command either reads
 * or is refused with the library's own exceptions, an error placed within the command. What reads and is written whole
 * in the DiceParser dialect reads back to the same text, and is rolled with a seeded randomizer, within the default
 * limits.
 */

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Exception\InvalidExpression;

require getenv('PHIL_DEV_AUTOLOAD');

$dice = new DiceRoller();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($dice): void {
    try {
        $pool = $dice->readRolisteam($input);
    } catch (InvalidExpression $invalidExpression) {
        if ($invalidExpression->input !== $input || $invalidExpression->position < 0 || $invalidExpression->position > mb_strlen($input)) {
            throw new LogicException(sprintf('The error of "%s" is placed at %d of "%s".', $input, $invalidExpression->position, $invalidExpression->input), $invalidExpression->getCode(), $invalidExpression);
        }

        return;
    } catch (DiceException) {
        // The refusal the reader promises for a command that goes past a limit.
        return;
    }

    $translation = $dice->translate($pool, 'diceparser');

    if ($translation->isComplete()) {
        $written = $translation->expressions[0];
        $again = $dice->translate($dice->readRolisteam($written), 'diceparser')->expressions[0];

        if ($again !== $written) {
            throw new LogicException(sprintf('The DiceParser form "%s" of "%s" reads back as "%s".', $written, $input, $again));
        }
    }

    try {
        $dice->roll($pool, new Randomizer(new Xoshiro256StarStar(1)));
    } catch (DiceException) {
        // A pool that reads may still be refused when rolled, such as a reroll that could never end.
    }
});
$config->setMaxLen(256);
