<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Contract;

use Tilttek\SecretRolls\Exception\JournalConflict;
use Tilttek\SecretRolls\HiddenRoll;
use Tilttek\SecretRolls\Journal\JournalEntry;
use Tilttek\SecretRolls\Journal\JournalHead;

/**
 * Where the journals of the scenes and the sealed openings are kept. The journal only grows: an entry is appended at the
 * position after the last one, never changed nor removed. The sealed openings are the store's secret: whoever reads
 * them knows the hidden results before the reveal.
 */
interface SecretRollStore
{
    /**
     * The entries of the scene's journal, by position; none for a scene that has none.
     *
     * @return list<JournalEntry>
     */
    public function entries(string $scene): array;

    /**
     * Where the scene's journal stands — its length, the hash of its last entry, whether it is closed —, read without
     * its entries: the store keeps it as it appends, so that a roll costs the same in a long scene as in a short one.
     * An empty head for a scene that has no entry.
     */
    public function head(string $scene): JournalHead;

    /**
     * Keeps the hidden roll sealed and appends the entry of its commitment, both or neither.
     *
     * @throws JournalConflict when the entry does not come right after the last one of its scene
     */
    public function commit(JournalEntry $entry, HiddenRoll $roll): void;

    /**
     * Appends an entry that reveals a roll or closes a scene.
     *
     * @throws JournalConflict when the entry does not come right after the last one of its scene
     */
    public function append(JournalEntry $entry): void;

    /**
     * The sealed roll of this identifier, or null when the store keeps none.
     */
    public function hidden(string $roll): ?HiddenRoll;
}
