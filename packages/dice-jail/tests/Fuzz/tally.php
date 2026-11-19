<?php

declare(strict_types=1);

/*
 * The fuzz target of the goodness-of-fit test, run by `phil-dev fuzz`: any bytes, read as the faces of a d6 or of a
 * d20, give a p-value between 0 and 1 when there are enough rolls, and an explanation in both languages, or refuse a
 * face the die cannot show with `InvalidRolls`.
 */

use Tilttek\DiceJail\Exception\InvalidRolls;
use Tilttek\DiceJail\Statistics\ExpectedFaces;
use Tilttek\DiceJail\Statistics\Explanation;
use Tilttek\DiceJail\Statistics\GoodnessOfFit;
use Tilttek\DiceJail\Statistics\Language;
use Tilttek\DiceJail\Statistics\Origin;
use Tilttek\DiceJail\Statistics\Tally;

require getenv('PHIL_DEV_AUTOLOAD');

$test = new GoodnessOfFit();
$explanation = new Explanation();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($test, $explanation): void {
    $faces = array_map(static fn (int $byte): int => $byte % 22, array_values(unpack('C*', $input) ?: []));
    $sides = 0 === count($faces) % 2 ? 6 : 20;

    try {
        $result = $test->test(Tally::of($faces), ExpectedFaces::uniform($sides));
    } catch (InvalidRolls) {
        return;
    }

    if (null !== $result->pValue && ($result->pValue < 0.0 || $result->pValue > 1.0 || is_nan($result->pValue))) {
        throw new LogicException('A p-value fell outside [0, 1].');
    }

    $explanation->explain($result, Language::French, Origin::Digital);
    $explanation->explain($result);
});
$config->setMaxLen(4096);
