<?php

declare(strict_types=1);

/*
 * The fuzz target of an army list, run by `phil-dev fuzz`: any text reads as a list that writes back to the same list,
 * or is refused with `InvalidArmyList`, never another exception; the list is then checked and exported against an
 * invented catalogue.
 */

use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\ArmyValidator;
use Tilttek\WargameArmy\Catalog\InMemoryCatalogueProvider;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\DetachmentType;
use Tilttek\WargameArmy\Exception\InvalidArmyList;
use Tilttek\WargameArmy\Export\HtmlExport;
use Tilttek\WargameArmy\Option;
use Tilttek\WargameArmy\OptionGroup;
use Tilttek\WargameArmy\Slot;
use Tilttek\WargameArmy\UnitEntry;

require getenv('PHIL_DEV_AUTOLOAD');

$catalogue = new Catalogue('c', 'C', '1', [
    new UnitEntry('u', 'U', ['t'], 10, 1, 5, 2, [new OptionGroup('g', 'G', [new Option('o', 'O', 3, 2, true)])]),
], [new DetachmentType('d', 'D', [new Slot('t', 0, 3)])], 0, 3);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($catalogue): void {
    try {
        $list = ArmyList::fromJson($input);
    } catch (InvalidArmyList) {
        return;
    }

    if (!ArmyList::fromJson($list->toJson())->equals($list)) {
        throw new LogicException('An army list does not read back as itself.');
    }

    $validation = new ArmyValidator(new InMemoryCatalogueProvider($catalogue))->validateAgainst($list, $catalogue);

    if (str_contains(new HtmlExport()->render($list, $catalogue, $validation), '<script')) {
        throw new LogicException('The HTML export let markup through.');
    }
});
$config->setMaxLen(4096);
