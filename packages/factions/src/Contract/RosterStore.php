<?php

declare(strict_types=1);

namespace Tilttek\Factions\Contract;

use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Exception\RosterConflict;
use Tilttek\Factions\History\Entry;
use Tilttek\Factions\Roster;

/**
 * Keeps the rosters, each at a version, and the history of their changes.
 */
interface RosterStore
{
    /**
     * The roster of the campaign, or null when none is kept.
     */
    public function roster(string $campaign): ?Roster;

    /**
     * Keeps the roster at the next version with the entries of its history, in one write, and returns it. A roster at
     * version 0 is new; any other is kept only when the store holds it at that version.
     *
     * @param list<Entry> $entries the changes the write makes, in the order they were made
     *
     * @throws RosterConflict when the roster is new and the store has one of its campaign, or the store holds it at
     *                        another version; nothing is kept
     */
    public function save(Roster $roster, array $entries = []): Roster;

    /**
     * Forgets the roster and its history, when the store holds it at its version.
     *
     * @throws RosterConflict when the store holds it at another version, or not at all; nothing is forgotten
     */
    public function delete(Roster $roster): void;

    /**
     * The entries of the history of a campaign that concern the faction, the newest first; at most the limit.
     *
     * @param int $limit 1 or more
     *
     * @return iterable<Entry>
     *
     * @throws InvalidRoster when the limit is below 1
     */
    public function history(string $campaign, string $faction, int $limit): iterable;
}
