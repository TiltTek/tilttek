<?php

declare(strict_types=1);

/*
 * The fuzz target of the shop codes, run by `phil-dev fuzz`: any input typed at a register either reads as the faces
 * of a box of a d6, a d10, a d12 and a d20, or is refused with the library's own exception. What reads is written back
 * to the same code, in capitals.
 */

use Tilttek\LootTables\Code\ShopCode;
use Tilttek\LootTables\Exception\InvalidCode;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;

require getenv('PHIL_DEV_AUTOLOAD');

$tables = [];

foreach ([6, 10, 12, 20] as $sides) {
    $tables[] = new LootTable('d'.$sides, 'D'.$sides, array_map(static fn (int $face): LootEntry => new LootEntry((string) $face, 'Face '.$face), range(1, $sides)));
}

$box = new LootBox('fuzz', 'Fuzz', $tables);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($box): void {
    try {
        $result = ShopCode::read($box, $input);
    } catch (InvalidCode) {
        // The refusal the reader promises for a code it cannot read.
        return;
    }

    if (ShopCode::write($result) !== strtoupper($input)) {
        throw new LogicException(sprintf('The code "%s" does not write back to itself.', $input));
    }
});
$config->setMaxLen(16);
