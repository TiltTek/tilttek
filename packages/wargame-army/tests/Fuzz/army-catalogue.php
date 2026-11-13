<?php

declare(strict_types=1);

/*
 * The fuzz target of a catalogue, run by `phil-dev fuzz`: any text reads as a catalogue that writes back to the same
 * catalogue, or is refused with `InvalidCatalogue`, never another exception; an empty list is then checked against it.
 */

use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\ArmyValidator;
use Tilttek\WargameArmy\Catalog\InMemoryCatalogueProvider;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $catalogue = Catalogue::fromJson($input);
    } catch (InvalidCatalogue) {
        return;
    }

    if (!Catalogue::fromJson($catalogue->toJson())->equals($catalogue)) {
        throw new LogicException('A catalogue does not read back as itself.');
    }

    new ArmyValidator(new InMemoryCatalogueProvider($catalogue))->validate(ArmyList::for($catalogue, 'fuzz', 'Fuzz', 100));
});
$config->setMaxLen(4096);
