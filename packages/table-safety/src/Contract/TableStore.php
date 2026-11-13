<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\TableSettings;

/**
 * Where each table's settings are kept.
 */
interface TableStore
{
    /**
     * The table's settings, or null for a table that kept none.
     */
    public function settings(ResourceReference $table): ?TableSettings;

    public function save(ResourceReference $table, TableSettings $settings): void;

    /**
     * Forgets the table's settings; nothing happens to a table that kept none.
     */
    public function remove(ResourceReference $table): void;
}
