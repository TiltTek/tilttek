<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Contract\TableStore;
use Tilttek\TableSafety\TableSettings;

/**
 * The tables' settings in `tilttek_table_safety_table`, through a DBAL connection, one row per table.
 */
final readonly class DbalTableStore implements TableStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function settings(ResourceReference $table): ?TableSettings
    {
        $settings = $this->connection->fetchOne(\sprintf('SELECT settings FROM %s WHERE table_ref = ?', TableSafetyTables::TABLES), [$table->toString()]);

        return false === $settings ? null : TableSettings::fromArray(Rows::decode($settings));
    }

    #[Override]
    public function save(ResourceReference $table, TableSettings $settings): void
    {
        $this->connection->transactional(function (Connection $connection) use ($table, $settings): void {
            $this->remove($table);
            $connection->insert(TableSafetyTables::TABLES, ['table_ref' => $table->toString(), 'settings' => Rows::json($settings->toArray())]);
        });
    }

    #[Override]
    public function remove(ResourceReference $table): void
    {
        $this->connection->delete(TableSafetyTables::TABLES, ['table_ref' => $table->toString()]);
    }
}
