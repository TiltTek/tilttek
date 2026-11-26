<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Contract;

use Tilttek\ScoreSheets\Exception\ConcurrentChange;
use Tilttek\ScoreSheets\MatchId;
use Tilttek\ScoreSheets\MatchSheet;

/**
 * The store of the sheets of matches: one sheet by match, written only over the version before it.
 */
interface MatchSheetStore
{
    public function find(MatchId $id): ?MatchSheet;

    /**
     * Writes the sheet when the stored one is its previous version — none for a sheet of version 0.
     *
     * @throws ConcurrentChange when another version was stored in between
     */
    public function save(MatchSheet $sheet): void;
}
