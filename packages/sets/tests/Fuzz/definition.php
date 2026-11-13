<?php

declare(strict_types=1);

/*
 * The fuzz target of a set definition, run by `phil-dev fuzz`: any text reads as a definition that writes back to the
 * same definition, or is refused with `InvalidSet`, never another exception.
 */

use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Holdings;
use Tilttek\Sets\SetDefinition;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $definition = SetDefinition::fromJson($input);
    } catch (InvalidSet) {
        return;
    }

    if (!SetDefinition::fromJson($definition->toJson())->equals($definition)) {
        throw new LogicException('A definition does not read back as itself.');
    }

    $evaluation = $definition->evaluate(Holdings::of(array_slice($definition->members, 0, 1)));

    if (1 !== $evaluation->ownedCount() || $evaluation->percent() > 100) {
        throw new LogicException('An evaluation counts what it was not given.');
    }
});
$config->setMaxLen(4096);
