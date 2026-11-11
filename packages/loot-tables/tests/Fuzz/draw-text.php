<?php

declare(strict_types=1);

/*
 * The fuzz target of the draw texts, run by `phil-dev fuzz`: any line read back from an order either reads as the
 * faces of a box of a d6, a d20 and a d100, or is refused with the library's own exception. What reads is written
 * again, and the line written reads back to the same faces.
 */

use Tilttek\LootTables\Code\DrawText;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Exception\InvalidCode;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;

require getenv('PHIL_DEV_AUTOLOAD');

$tables = [];

foreach ([6, 20, 100] as $sides) {
    $tables[] = new LootTable('d'.$sides, 'D'.$sides, array_map(static fn (int $face): LootEntry => new LootEntry((string) $face, 'Face '.$face), range(1, $sides)));
}

$box = new LootBox('fuzz', 'Fuzz', $tables);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($box): void {
    try {
        $result = DrawText::read($box, $input, DrawSource::Verifiable);
    } catch (InvalidCode) {
        // The refusal the reader promises for a line it cannot read.
        return;
    }

    $written = DrawText::write($result);

    if (DrawText::read($box, $written, DrawSource::Verifiable)->faces() !== $result->faces()) {
        throw new LogicException(sprintf('The draw text "%s" of "%s" does not read back to its faces.', $written, $input));
    }
});
$config->setMaxLen(128);
