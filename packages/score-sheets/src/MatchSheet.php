<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

/**
 * What a store keeps of the sheet of a match: its declaration, its entries, its status, its revision — the number of
 * times it was closed — and its version, one more at every change. Scores are never stored: they are computed.
 */
final readonly class MatchSheet
{
    public function __construct(
        public MatchId $id,
        public SheetDefinition $sheet,
        public Entries $entries,
        public SheetStatus $status = SheetStatus::Prepared,
        public int $revision = 0,
        public int $version = 0,
    ) {
    }

    /**
     * Whether the players may see it: once published, and after it is closed.
     */
    public function isVisible(): bool
    {
        return SheetStatus::Prepared !== $this->status;
    }

    public function withEntries(Entries $entries): self
    {
        return new self($this->id, $this->sheet, $entries, $this->status, $this->revision, $this->version + 1);
    }

    /**
     * The sheet in a new status; closing it counts one more revision.
     */
    public function withStatus(SheetStatus $status): self
    {
        return new self($this->id, $this->sheet, $this->entries, $status, SheetStatus::Closed === $status ? $this->revision + 1 : $this->revision, $this->version + 1);
    }
}
