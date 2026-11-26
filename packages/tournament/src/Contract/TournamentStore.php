<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Contract;

use Tilttek\Tournament\Exception\TournamentConflict;
use Tilttek\Tournament\Exception\TournamentNotFound;
use Tilttek\Tournament\TournamentState;

/**
 * Where tournaments are kept, each at a version, so that two changes made at once never overwrite each other.
 * `Store\InMemoryTournamentStore` keeps them in memory, for tests; an application keeps them in its database.
 */
interface TournamentStore
{
    /**
     * @throws TournamentNotFound when no tournament has the identifier
     */
    public function get(string $id): TournamentState;

    /**
     * Keeps the tournament at the next version: a state at version 0 is new, and the store has no tournament of its
     * identifier; any other is kept only when the store holds it at that version.
     *
     * @throws TournamentConflict when the tournament changed or was added since it was read; nothing is kept
     */
    public function save(TournamentState $state): void;
}
