<?php

declare(strict_types=1);

/*
 * The fuzz target of the dice parser, run by `phil-dev fuzz`: any input either parses or is refused with the
 * library's own exceptions. What parses is written in its canonical form, which parses again to the same form, and is
 * rolled with a seeded randomizer, within the default limits.
 */

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;

require getenv('PHIL_DEV_AUTOLOAD');

$dice = new DiceRoller();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($dice): void {
    try {
        $pool = $dice->parse($input);
    } catch (DiceException) {
        // The refusal the parser promises for an input it cannot read or that goes past a limit.
        return;
    }

    $written = $dice->write($pool);

    if ($dice->write($dice->parse($written)) !== $written) {
        throw new LogicException(sprintf('The canonical form "%s" of "%s" does not parse back to itself.', $written, $input));
    }

    try {
        $dice->roll($pool, new Randomizer(new Xoshiro256StarStar(1)));
    } catch (DiceException) {
        // A pool that parses may still be refused when rolled, such as a reroll that could never end.
    }
});
$config->setMaxLen(256);
