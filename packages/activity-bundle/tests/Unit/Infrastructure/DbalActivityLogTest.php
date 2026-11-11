<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Log\ActivityLog;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\ActivityTables;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\DbalActivityLog;
use UnexpectedValueException;

/**
 * The journal on SQLite in memory, its tables created from `ActivityTables`.
 */
#[CoversClass(DbalActivityLog::class)]
#[CoversClass(ActivityTables::class)]
final class DbalActivityLogTest extends ActivityLogTestCase
{
    private Connection $connection;

    #[Override]
    protected function setUp(): void
    {
        $this->connection = Database::connection();
    }

    public function testTheTables(): void
    {
        $schema = $this->connection->createSchemaManager()->introspectSchema();
        $entries = $schema->getTable(ActivityTables::ENTRIES);

        self::assertSame(['position'], $entries->getPrimaryKey()?->getColumns());
        self::assertTrue($entries->getColumn('position')->getAutoincrement());
        self::assertSame(
            ['position' => [null, true], 'kind' => [16, true], 'idempotency_key' => [255, true], 'member' => [320, true], 'verb' => [128, true], 'object' => [320, false], 'quantity' => [null, true], 'context' => [null, true], 'occurred_at' => [null, true], 'recorded_at' => [null, true]],
            array_merge(...array_map(static fn (Column $column): array => [$column->getName() => [$column->getLength(), $column->getNotnull()]], $entries->getColumns())),
        );
        self::assertSame(['idempotency_key', 'kind'], $entries->getIndex('tilttek_activity_entry_key')->getColumns());
        self::assertTrue($entries->getIndex('tilttek_activity_entry_key')->isUnique());
        self::assertSame(['member'], $entries->getIndex('tilttek_activity_entry_member')->getColumns());
        self::assertSame(['object'], $entries->getIndex('tilttek_activity_entry_object')->getColumns());
        self::assertSame(['verb', 'occurred_at'], $entries->getIndex('tilttek_activity_entry_verb')->getColumns());

        $checkpoints = $schema->getTable(ActivityTables::CHECKPOINTS);
        self::assertSame(['projection'], $checkpoints->getPrimaryKey()?->getColumns());
        self::assertSame(
            ['projection' => [64, true], 'position' => [null, true], 'updated_at' => [null, true]],
            array_merge(...array_map(static fn (Column $column): array => [$column->getName() => [$column->getLength(), $column->getNotnull()]], $checkpoints->getColumns())),
        );
    }

    public function testTablesAlreadyThereAreLeftAsTheyAre(): void
    {
        $schema = ActivityTables::configure(new Schema());
        $again = ActivityTables::configure($schema);

        self::assertCount(2, $again->getTables());
        self::assertSame($schema->getTable(ActivityTables::ENTRIES)->getColumns(), $again->getTable(ActivityTables::ENTRIES)->getColumns());
    }

    public function testTheInsertionWritesNothingWhenTheKeyIsThere(): void
    {
        $into = 'INTO tilttek_activity_entry (kind, idempotency_key, member, verb, object, quantity, context, occurred_at, recorded_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';

        self::assertSame('INSERT '.$into.' ON CONFLICT (idempotency_key, kind) DO NOTHING', DbalActivityLog::insertion(new PostgreSQLPlatform()));
        self::assertSame('INSERT IGNORE '.$into, DbalActivityLog::insertion(new MySQLPlatform()));
        self::assertSame('INSERT IGNORE '.$into, DbalActivityLog::insertion(new MariaDBPlatform()));
        self::assertSame('INSERT OR IGNORE '.$into, DbalActivityLog::insertion(new SQLitePlatform()));
        self::assertSame('INSERT '.$into, DbalActivityLog::insertion(new SQLServerPlatform()));
        self::assertSame('INSERT '.$into, DbalActivityLog::insertion(new OraclePlatform()));
    }

    public function testDatesAreWrittenInUtc(): void
    {
        $log = $this->log();
        $appended = $log->append($this->activity('a', 'member:1', 'game.played'), new DateTimeImmutable('2026-11-07 07:00:00-05:00'));
        $log->append(Activity::of('b', 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 09:30:00-05:00')), new DateTimeImmutable('2026-11-07 07:00:00-05:00'));

        self::assertSame('2026-11-07T12:00:00+00:00', $appended->entry->at->format(\DATE_ATOM));
        self::assertSame('2026-11-07 12:00:00', $this->connection->fetchOne('SELECT recorded_at FROM tilttek_activity_entry WHERE position = 1'));
        self::assertSame('2026-11-07 14:30:00', $this->connection->fetchOne('SELECT occurred_at FROM tilttek_activity_entry WHERE position = 2'));
        self::assertSame('2026-11-07T14:30:00+00:00', $log->find(IdempotencyKey::fromString('b'))?->activity->occurredAt->format(\DATE_ATOM));
        self::assertSame(['b'], array_map(static fn (Entry $entry): string => $entry->activity->key->value, $log->read(0, 10, Filter::all()->since(new DateTimeImmutable('2026-11-07 09:00:00-05:00')))));
        self::assertSame(['a'], array_map(static fn (Entry $entry): string => $entry->activity->key->value, $log->read(0, 10, Filter::all()->until(new DateTimeImmutable('2026-11-07 09:00:00-05:00')))));
    }

    public function testAnEmptyContextIsKept(): void
    {
        $log = $this->log();
        $log->append($this->activity('a', 'member:1', 'game.played'), $this->at());

        self::assertSame('[]', $this->connection->fetchOne('SELECT context FROM tilttek_activity_entry'));
        self::assertSame([], $log->find(IdempotencyKey::fromString('a'))?->activity->context->values);
    }

    public function testARowWhoseContextDoesNotReadIsRefused(): void
    {
        foreach (['5' => 'A context column holds JSON that is not an object.', '{"a":[1]}' => 'A context column holds a value that is not an integer, a boolean or a string.', '{"0":1}' => 'A context column holds a value that is not an integer, a boolean or a string.', '{"a":1.5}' => 'A context column holds a value that is not an integer, a boolean or a string.'] as $context => $message) {
            $this->connection->executeStatement('DELETE FROM tilttek_activity_entry');
            $this->connection->executeStatement("INSERT INTO tilttek_activity_entry (kind, idempotency_key, member, verb, object, quantity, context, occurred_at, recorded_at) VALUES ('recorded', 'k', 'member:1', 'a', NULL, 1, ?, '2026-11-07 10:00:00', '2026-11-07 10:00:00')", [(string) $context]);

            try {
                $this->log()->read(0, 10, Filter::all());
                self::fail('The context '.$context.' was read.');
            } catch (UnexpectedValueException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        $this->connection->executeStatement('DELETE FROM tilttek_activity_entry');
        $this->connection->executeStatement("INSERT INTO tilttek_activity_entry (kind, idempotency_key, member, verb, object, quantity, context, occurred_at, recorded_at) VALUES ('recorded', 'k', 'member:1', 'a', NULL, 1, '{\"b\":\"x\",\"a\":true,\"c\":2}', '2026-11-07 10:00:00', '2026-11-07 10:00:00')");
        self::assertSame(['a' => true, 'b' => 'x', 'c' => 2], $this->log()->read(0, 10, Filter::all())[0]->activity->context->values);
    }

    #[Override]
    protected function log(): ActivityLog
    {
        return new DbalActivityLog($this->connection);
    }
}
