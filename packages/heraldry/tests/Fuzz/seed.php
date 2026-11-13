<?php

declare(strict_types=1);

/*
 * The fuzz target of the seeds, run by `phil-dev fuzz`: any text is refused with `InvalidSeed`, or read as a seed that
 * writes back to the same digits in lowercase and draws arms.
 */

use Tilttek\Heraldry\Exception\InvalidSeed;
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Generator\Seed;

require getenv('PHIL_DEV_AUTOLOAD');

$generator = new CoatGenerator();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($generator): void {
    try {
        $seed = Seed::fromHex($input);
    } catch (InvalidSeed) {
        return;
    }

    if ($seed->hex() !== strtolower($input)) {
        throw new LogicException('A seed does not write back to its digits.');
    }

    $generator->generate($seed);
});
$config->setMaxLen(128);
