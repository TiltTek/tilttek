<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Store;

use Override;
use Tilttek\Encounter\Contract\EncounterStore;
use Tilttek\Encounter\Encounter;
use Tilttek\Encounter\Exception\EncounterConflict;

/**
 * The encounters in memory, for tests: they last as long as the object.
 */
final class InMemoryEncounterStore implements EncounterStore
{
    /** @var array<string, Encounter> */
    private array $encounters = [];

    #[Override]
    public function encounter(string $id): ?Encounter
    {
        return $this->encounters[$id] ?? null;
    }

    #[Override]
    public function save(Encounter $encounter): Encounter
    {
        $kept = $this->encounters[$encounter->id] ?? null;
        if (0 === $encounter->revision && null !== $kept) {
            throw EncounterConflict::exists($encounter->id);
        }

        if ($encounter->revision !== ($kept->revision ?? 0)) {
            throw EncounterConflict::changed($encounter->id);
        }

        return $this->encounters[$encounter->id] = $encounter->withRevision($encounter->revision + 1);
    }

    #[Override]
    public function remove(Encounter $encounter): void
    {
        if ($encounter->revision !== ($this->encounters[$encounter->id]->revision ?? null)) {
            throw EncounterConflict::changed($encounter->id);
        }

        unset($this->encounters[$encounter->id]);
    }
}
