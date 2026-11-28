<?php

declare(strict_types=1);

namespace Tilttek\Factions\Store;

use Override;
use Tilttek\Factions\Contract\RosterStore;
use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Exception\RosterConflict;
use Tilttek\Factions\History\Entry;
use Tilttek\Factions\Roster;

/**
 * The rosters and their history in memory, for tests: they last as long as the object.
 */
final class InMemoryRosterStore implements RosterStore
{
    /** @var array<string, Roster> by campaign */
    private array $rosters = [];

    /** @var array<string, list<Entry>> by campaign, in the order kept */
    private array $entries = [];

    #[Override]
    public function roster(string $campaign): ?Roster
    {
        return $this->rosters[$campaign] ?? null;
    }

    #[Override]
    public function save(Roster $roster, array $entries = []): Roster
    {
        if ($roster->version !== ($this->rosters[$roster->campaign]->version ?? 0)) {
            throw RosterConflict::changed($roster->campaign);
        }

        $this->entries[$roster->campaign] = [...$this->entries[$roster->campaign] ?? [], ...$entries];

        return $this->rosters[$roster->campaign] = $roster->withVersion($roster->version + 1);
    }

    #[Override]
    public function delete(Roster $roster): void
    {
        if ($roster->version !== ($this->rosters[$roster->campaign]->version ?? null)) {
            throw RosterConflict::changed($roster->campaign);
        }

        unset($this->rosters[$roster->campaign], $this->entries[$roster->campaign]);
    }

    #[Override]
    public function history(string $campaign, string $faction, int $limit): iterable
    {
        if ($limit < 1) {
            throw InvalidRoster::because('A store reads at least one entry at once.');
        }

        $entries = array_filter($this->entries[$campaign] ?? [], static fn (Entry $entry): bool => $entry->concerns($faction));

        return \array_slice(array_reverse($entries), 0, $limit);
    }
}
