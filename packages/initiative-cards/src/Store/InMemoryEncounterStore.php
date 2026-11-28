<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Store;

use Override;
use Tilttek\InitiativeCards\Contract\EncounterStore;
use Tilttek\InitiativeCards\Encounter;
use Tilttek\InitiativeCards\Exception\EncounterConflict;

/**
 * The encounters in memory, for tests: they last as long as the object.
 */
final class InMemoryEncounterStore implements EncounterStore
{
    /** @var array<string, Encounter> by identifier */
    private array $encounters = [];

    #[Override]
    public function encounter(string $id): ?Encounter
    {
        return $this->encounters[$id] ?? null;
    }

    #[Override]
    public function save(Encounter $encounter): Encounter
    {
        if ($encounter->version !== ($this->encounters[$encounter->id]->version ?? 0)) {
            throw EncounterConflict::changed($encounter->id);
        }

        return $this->encounters[$encounter->id] = $encounter->withVersion($encounter->version + 1);
    }
}
