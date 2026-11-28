<?php

declare(strict_types=1);

namespace Tilttek\Factions\Tests\Unit\Fixture;

use Override;
use Tilttek\Factions\Contract\RosterStore;
use Tilttek\Factions\Exception\RosterConflict;
use Tilttek\Factions\Roster;

/**
 * A store whose roster another request always changed first: it reads one, and keeps nothing.
 */
final readonly class StaleStore implements RosterStore
{
    public function __construct(private Roster $roster)
    {
    }

    #[Override]
    public function roster(string $campaign): Roster
    {
        return $this->roster;
    }

    #[Override]
    public function save(Roster $roster, array $entries = []): Roster
    {
        throw RosterConflict::changed($roster->campaign);
    }

    #[Override]
    public function delete(Roster $roster): void
    {
        throw RosterConflict::changed($roster->campaign);
    }

    #[Override]
    public function history(string $campaign, string $faction, int $limit): iterable
    {
        return [];
    }
}
