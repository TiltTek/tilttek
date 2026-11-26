<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Store;

use Tilttek\ScoreSheets\Contract\MatchSheetStore;
use Tilttek\ScoreSheets\Exception\ConcurrentChange;
use Tilttek\ScoreSheets\MatchId;
use Tilttek\ScoreSheets\MatchSheet;

/**
 * A store in memory, for tests: it keeps its sheets for the life of the object, which a long-running worker must never
 * share between requests.
 */
final class InMemoryMatchSheetStore implements MatchSheetStore
{
    /** @var array<string, MatchSheet> */
    private array $sheets = [];

    public function find(MatchId $id): ?MatchSheet
    {
        return $this->sheets[$id->value] ?? null;
    }

    public function save(MatchSheet $sheet): void
    {
        $stored = $this->sheets[$sheet->id->value] ?? null;

        if (($stored->version ?? -1) !== $sheet->version - 1) {
            throw ConcurrentChange::because(\sprintf('The sheet of the match "%s" changed since version %d was read.', $sheet->id->value, $sheet->version - 1));
        }

        $this->sheets[$sheet->id->value] = $sheet;
    }
}
