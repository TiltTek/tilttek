<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Journal;

/**
 * Where a scene's journal stands, without its entries: how many it holds, the hash of the last one, which the next
 * entry carries, and whether the scene is closed. A roll reads it instead of the whole journal.
 */
final readonly class JournalHead
{
    public function __construct(
        public string $scene,
        public int $length = 0,
        public string $hash = JournalEntry::GENESIS,
        public bool $closed = false,
    ) {
    }

    /**
     * The head once the entry is appended after the last one.
     */
    public function after(JournalEntry $entry): self
    {
        return new self($this->scene, $entry->position, $entry->hash, $this->closed || EntryKind::Closed === $entry->kind);
    }

    /**
     * The position of the next entry.
     */
    public function nextPosition(): int
    {
        return $this->length + 1;
    }
}
