<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomTables;

/**
 * The columns and the indexes of the five tables.
 */
#[CoversClass(EscapeRoomTables::class)]
final class EscapeRoomTablesTest extends TestCase
{
    public function testTheGameTable(): void
    {
        $table = $this->table(EscapeRoomTables::GAMES);

        self::assertSame(['id' => [64, true], 'room' => [64, true], 'team' => [320, true], 'session' => [255, false], 'deadline' => [null, false], 'state' => [null, true]], $this->columns($table));
        self::assertSame([EscapeRoomTables::GAMES.'_deadline' => ['deadline'], EscapeRoomTables::GAMES.'_team' => ['team']], $this->indexes($table));
    }

    public function testTheResultTable(): void
    {
        $table = $this->table(EscapeRoomTables::RESULTS);

        self::assertSame(
            ['game' => [64, true], 'room' => [64, true], 'team' => [320, true], 'session' => [255, false], 'final_seconds' => [null, true], 'played_seconds' => [null, true], 'penalty_seconds' => [null, true], 'hints' => [null, true], 'skips' => [null, true], 'escaped_at' => [null, true], 'escaped_micro' => [null, true], 'public' => [null, true]],
            $this->columns($table),
        );
        self::assertSame(
            [EscapeRoomTables::RESULTS.'_rank' => ['room', 'public', 'final_seconds', 'hints', 'escaped_at', 'escaped_micro'], EscapeRoomTables::RESULTS.'_team' => ['team']],
            $this->indexes($table),
        );
    }

    public function testTheScreenTable(): void
    {
        $table = $this->table(EscapeRoomTables::SCREENS);

        self::assertSame(
            ['game' => [64, true], 'kind' => [16, true], 'host' => [320, true], 'shown_at' => [null, true], 'puzzle' => [255, false], 'tier' => [null, false], 'penalty' => [null, true], 'message' => [1_120, false]],
            $this->columns($table),
        );
        self::assertSame([EscapeRoomTables::SCREENS.'_host' => ['host']], $this->indexes($table));
    }

    public function testTheBookingTable(): void
    {
        $table = $this->table(EscapeRoomTables::BOOKINGS);

        self::assertSame(
            ['id' => [255, true], 'session' => [255, true], 'room' => [64, true], 'party' => [320, true], 'seats' => [null, true], 'private' => [null, true], 'position' => [null, true], 'booked_at' => [null, true]],
            $this->columns($table),
        );
        self::assertSame([EscapeRoomTables::BOOKINGS.'_session' => ['session', 'position'], EscapeRoomTables::BOOKINGS.'_party' => ['party']], $this->indexes($table));
    }

    public function testTheSessionLockTable(): void
    {
        $table = $this->table(EscapeRoomTables::SESSION_LOCKS);

        self::assertSame(['session' => [255, true], 'locked_at' => [null, true]], $this->columns($table));
        self::assertSame([], $this->indexes($table));
    }

    private function table(string $name): Table
    {
        return EscapeRoomTables::configure(Schema::editor()->create())->getTable($name);
    }

    /**
     * @return array<string, array{?int, bool}>
     */
    private function columns(Table $table): array
    {
        $columns = [];

        foreach ($table->getColumns() as $column) {
            $columns[$column->getObjectName()->toString()] = [$column->getLength(), $column->getNotnull()];
        }

        return $columns;
    }

    /**
     * The indexes other than the primary key, by name, with their columns.
     *
     * @return array<string, list<string>>
     */
    private function indexes(Table $table): array
    {
        $indexes = [];

        foreach ($table->getIndexes() as $index) {
            $name = $index->getObjectName()->toString();

            if ('primary' === strtolower($name)) {
                continue;
            }

            $indexes[$name] = array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $index->getIndexedColumns());
        }

        return $indexes;
    }
}
