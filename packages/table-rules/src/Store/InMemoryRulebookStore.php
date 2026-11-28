<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Store;

use Override;
use Tilttek\TableRules\Contract\RulebookStore;
use Tilttek\TableRules\Exception\RulesConflict;
use Tilttek\TableRules\Rulebook;
use Tilttek\TableRules\RulebookVersion;

/**
 * The rulebooks in memory, for tests: they last as long as the object.
 */
final class InMemoryRulebookStore implements RulebookStore
{
    /** @var array<string, Rulebook> by table */
    private array $rulebooks = [];

    /** @var array<string, array<int, RulebookVersion>> by table, then by number */
    private array $versions = [];

    #[Override]
    public function rulebook(string $table): ?Rulebook
    {
        return $this->rulebooks[$table] ?? null;
    }

    #[Override]
    public function version(string $table, int $number): ?RulebookVersion
    {
        return $this->versions[$table][$number] ?? null;
    }

    #[Override]
    public function save(Rulebook $rulebook, ?RulebookVersion $published = null): Rulebook
    {
        if ($rulebook->revision !== ($this->rulebooks[$rulebook->table]->revision ?? 0)) {
            throw RulesConflict::changed($rulebook->table);
        }

        if ($published instanceof RulebookVersion) {
            $this->versions[$rulebook->table][$published->number] = $published;
        }

        return $this->rulebooks[$rulebook->table] = $rulebook->withRevision($rulebook->revision + 1);
    }
}
