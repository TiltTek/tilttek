<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\TableSafetyTables;

#[CoversClass(TableSafetyTables::class)]
final class TablesTest extends TestCase
{
    public function testTheFourTablesAreCreated(): void
    {
        $manager = Database::connection()->createSchemaManager();

        self::assertTrue($manager->tablesExist([TableSafetyTables::TABLES, TableSafetyTables::PARTICIPATIONS, TableSafetyTables::SIGNALS, TableSafetyTables::FEEDBACK]));

        $signals = $manager->introspectTableByUnquotedName(TableSafetyTables::SIGNALS);
        $columns = [];

        foreach ($signals->getColumns() as $column) {
            $columns[trim($column->getObjectName()->toString(), '"')] = $column->getNotnull();
        }

        self::assertSame(['id' => true, 'table_ref' => true, 'kind' => true, 'audience' => true, 'raised_at' => true, 'raised_us' => true, 'raised_by' => false, 'acknowledged_at' => false], $columns);
        self::assertSame(['id'], array_map(static fn (object $name): string => trim($name->toString(), '"'), $signals->getPrimaryKeyConstraint()?->getColumnNames() ?? []));

        $indexes = array_map(static fn (object $index): string => trim($index->getObjectName()->toString(), '"'), $signals->getIndexes());
        sort($indexes);
        self::assertContains('tilttek_table_safety_signal_table', $indexes);
        self::assertContains('tilttek_table_safety_signal_raised_by', $indexes);

        $participations = $manager->introspectTableByUnquotedName(TableSafetyTables::PARTICIPATIONS);
        self::assertSame(['table_ref', 'participant'], array_map(static fn (object $name): string => trim($name->toString(), '"'), $participations->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertTrue($participations->hasColumn('first_answered_at'));
        self::assertTrue($participations->getColumn('first_answered_at')->getNotnull());
        $feedback = $manager->introspectTableByUnquotedName(TableSafetyTables::FEEDBACK);
        self::assertSame(['table_ref', 'session', 'participant'], array_map(static fn (object $name): string => trim($name->toString(), '"'), $feedback->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        $tables = $manager->introspectTableByUnquotedName(TableSafetyTables::TABLES);
        self::assertSame(['table_ref'], array_map(static fn (object $name): string => trim($name->toString(), '"'), $tables->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
    }

    public function testTheTextColumnsHaveRoomForTheirValues(): void
    {
        $manager = Database::connection()->createSchemaManager();
        $lengths = [];

        foreach ([TableSafetyTables::SIGNALS, TableSafetyTables::FEEDBACK] as $name) {
            foreach ($manager->introspectTableByUnquotedName($name)->getColumns() as $column) {
                $lengths[$name.'.'.trim($column->getObjectName()->toString(), '"')] = $column->getLength();
            }
        }

        self::assertSame(32, $lengths[TableSafetyTables::SIGNALS.'.id']);
        self::assertSame(32, $lengths[TableSafetyTables::SIGNALS.'.kind']);
        self::assertSame(16, $lengths[TableSafetyTables::SIGNALS.'.audience']);
        self::assertSame(64, $lengths[TableSafetyTables::FEEDBACK.'.session']);
    }

    public function testTablesAlreadyThereAreLeftAsTheyAre(): void
    {
        $connection = Database::connection();
        $schema = $connection->createSchemaManager()->introspectSchema();

        self::assertCount(4, TableSafetyTables::configure($schema)->getTables());
        self::assertSame($schema->getTable(TableSafetyTables::SIGNALS)->getColumns(), TableSafetyTables::configure($schema)->getTable(TableSafetyTables::SIGNALS)->getColumns());
    }
}
