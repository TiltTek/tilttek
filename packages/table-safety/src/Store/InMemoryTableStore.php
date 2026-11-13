<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Contract\TableStore;
use Tilttek\TableSafety\TableSettings;

/**
 * The tables' settings in memory, for tests and for a process that keeps nothing.
 */
final class InMemoryTableStore implements TableStore
{
    /** @var array<string, TableSettings> by table */
    private array $settings = [];

    #[Override]
    public function settings(ResourceReference $table): ?TableSettings
    {
        return $this->settings[$table->toString()] ?? null;
    }

    #[Override]
    public function save(ResourceReference $table, TableSettings $settings): void
    {
        $this->settings[$table->toString()] = $settings;
    }

    #[Override]
    public function remove(ResourceReference $table): void
    {
        unset($this->settings[$table->toString()]);
    }
}
