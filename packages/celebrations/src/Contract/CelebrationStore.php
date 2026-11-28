<?php

declare(strict_types=1);

namespace Tilttek\Celebrations\Contract;

use Tilttek\Celebrations\Entry;
use Tilttek\Celebrations\EntryState;
use Tilttek\Celebrations\Preferences;

/**
 * Where the inboxes and the members' preferences are kept. Members are named by their references written
 * `kind:identifier`; an entry by its member, its source and its occurrence, which are unique together.
 *
 * The library checks an entry is new and the inbox's bounds before it writes: an application that serves concurrent
 * requests runs each act of `Celebrations` in a transaction, or under a lock on the member, and keeps a unique index on
 * the member, the source and the occurrence, so that an achievement told twice at once is kept once. An implementation
 * keeps no state between two requests beside its storage.
 */
interface CelebrationStore
{
    public function find(string $member, string $source, string $occurrence): ?Entry;

    /**
     * The member's entries, all of them or those in a state, by sequence, the first received first.
     *
     * @return list<Entry>
     */
    public function entries(string $member, ?EntryState $state = null): array;

    public function countEntries(string $member, ?EntryState $state = null): int;

    /**
     * A page of the member's entries, the last received first: at most `$limit`, those received before the sequence
     * `$before` when it is given.
     *
     * @param positive-int $limit
     *
     * @return list<Entry>
     */
    public function latest(string $member, int $limit, ?int $before = null): array;

    /**
     * The sequence of the member's next entry: one more than any the member was given, never reused, even after the
     * entry was dropped or the member forgotten.
     *
     * @return positive-int
     */
    public function nextSequence(string $member): int;

    /**
     * Writes the entry, replacing the entry of the same member, source and occurrence.
     */
    public function save(Entry $entry): void;

    public function remove(string $member, string $source, string $occurrence): void;

    public function preferences(string $member): ?Preferences;

    public function savePreferences(string $member, Preferences $preferences): void;

    /**
     * Removes the member's entries and preferences.
     */
    public function forget(string $member): void;
}
