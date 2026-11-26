<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Store;

use Override;
use Tilttek\Tournament\Contract\TournamentStore;
use Tilttek\Tournament\Exception\TournamentConflict;
use Tilttek\Tournament\Exception\TournamentNotFound;
use Tilttek\Tournament\TournamentState;

/**
 * The tournaments in memory, for tests: they last as long as the object.
 */
final class InMemoryTournamentStore implements TournamentStore
{
    /** @var array<string, TournamentState> by tournament */
    private array $states = [];

    #[Override]
    public function get(string $id): TournamentState
    {
        return $this->states[$id] ?? throw TournamentNotFound::withId($id);
    }

    #[Override]
    public function save(TournamentState $state): void
    {
        $id = $state->tournament->id;

        if ($state->version !== ($this->states[$id]->version ?? 0)) {
            throw TournamentConflict::changed($id);
        }

        $this->states[$id] = $state->withVersion($state->version + 1);
    }
}
