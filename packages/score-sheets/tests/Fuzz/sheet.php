<?php

declare(strict_types=1);

/*
 * The fuzz target of the sheet declarations read from JSON, run by `phil-dev fuzz`: any text is either refused with
 * the library's own exceptions or gives a declaration that writes itself back to the same one, and a blank sheet and a
 * scoreboard computed without an exception the library does not promise.
 */

use Tilttek\ScoreSheets\BlankSheet;
use Tilttek\ScoreSheets\Entries;
use Tilttek\ScoreSheets\Exception\ScoreSheetsException;
use Tilttek\ScoreSheets\ParticipantId;
use Tilttek\ScoreSheets\Scoring\Scorer;
use Tilttek\ScoreSheets\SheetDefinition;

require getenv('PHIL_DEV_AUTOLOAD');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $sheet = SheetDefinition::fromJson($input);
    } catch (ScoreSheetsException) {
        // The refusals the declaration promises for a text it cannot read.
        return;
    }

    if (SheetDefinition::fromArray($sheet->toArray()) !== $sheet) {
        throw new LogicException('A declaration does not read back to itself.');
    }

    new BlankSheet($sheet)->toArray();
    $entries = new Entries([new ParticipantId('a'), new ParticipantId('b')]);

    foreach ($sheet->categories() as $category) {
        if ($category->isEntered()) {
            $entries = $entries->with($sheet, new ParticipantId('a'), $category->key, $category->max);
        }
    }

    try {
        $board = new Scorer()->score($sheet, $entries);
    } catch (ScoreSheetsException) {
        // A total too large to compute.
        return;
    }

    if (2 !== count($board->participants)) {
        throw new LogicException('A participant was lost.');
    }
});
$config->setMaxLen(1024);
