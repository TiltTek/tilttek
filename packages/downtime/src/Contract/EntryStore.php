<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Contract;

use Tilttek\Downtime\Entry;
use Tilttek\Downtime\Exception\DowntimeConflict;
use Tilttek\Downtime\Exception\InvalidDowntime;

/**
 * Keeps the entries of the logs, each at a version, and reads them in the order they were chosen.
 */
interface EntryStore
{
    /**
     * The entry, or null when none has the identifier.
     */
    public function entry(string $id): ?Entry;

    /**
     * Keeps the entries at their next version, all of them or none, and returns them in the order given. An entry at
     * version 0 is new; any other is kept only when the store holds it at that version.
     *
     * @param list<Entry> $entries
     *
     * @return list<Entry>
     *
     * @throws DowntimeConflict when an entry is new and the store has one of its identifier, or the store holds one at
     *                          another version; nothing is kept
     */
    public function save(array $entries): array;

    /**
     * The log of a character in a campaign: its entries in the order they were chosen, at most the limit.
     *
     * @param int $limit 1 or more
     *
     * @return list<Entry>
     *
     * @throws InvalidDowntime when the limit is below 1
     */
    public function entriesOf(string $campaign, string $character, int $limit): array;

    /**
     * The open entries of a character in a campaign — queued, awaiting a roll or resolved —, in the order they were
     * chosen, at most the limit.
     *
     * @param int $limit 1 or more
     *
     * @return list<Entry>
     *
     * @throws InvalidDowntime when the limit is below 1
     */
    public function openOf(string $campaign, string $character, int $limit): array;

    /**
     * The queued entries of a campaign, every character's, in the order they were chosen, at most the limit.
     *
     * @param int $limit 1 or more
     *
     * @return list<Entry>
     *
     * @throws InvalidDowntime when the limit is below 1
     */
    public function queuedIn(string $campaign, int $limit): array;
}
