<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Infrastructure;

use DateInterval;
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
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\Projector;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\ActivityTables;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\DbalActivityLog;
use Tilttek\ActivityBundle\Tests\Unit\Fixture\Clock;
use Tilttek\ActivityBundle\Tests\Unit\Fixture\Counter;
use Tilttek\ActivityBundle\Tests\Unit\Fixture\RacingConnection;
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

    public function testAGestureRecordedOrCancelledAgainTakesNoPosition(): void
    {
        $log = $this->log();
        $log->append($this->activity('a', 'member:1', 'game.played'), $this->at());
        $log->append($this->activity('a', 'member:1', 'game.played'), $this->at());
        $log->cancel(IdempotencyKey::fromString('a'), $this->at());
        $log->cancel(IdempotencyKey::fromString('a'), $this->at());
        $log->append($this->activity('b', 'member:1', 'game.played'), $this->at());

        self::assertSame([1, 2, 3], $this->connection->fetchFirstColumn('SELECT position FROM tilttek_activity_entry ORDER BY position'));
    }

    public function testAGestureRecordedByAnotherProcessBetweenTheReadAndTheInsertionIsNotNew(): void
    {
        $connection = Database::connection(RacingConnection::class);
        $log = new DbalActivityLog($connection);
        $connection->race = true;
        $appended = $log->append($this->activity('a', 'member:1', 'game.played'), $this->at());

        self::assertFalse($appended->new);
        self::assertSame(1, $appended->entry->position);

        $connection->race = true;
        self::assertFalse($log->cancel(IdempotencyKey::fromString('a'), $this->at())?->new);
    }

    public function testTheReadingWaitsForAPositionTakenByATransactionNotYetCommitted(): void
    {
        $clock = new Clock('2026-11-07 12:00:00 UTC');
        $log = new DbalActivityLog($this->connection, $clock);
        $this->row(1, '2026-11-07 11:00:00');
        $this->row(3, '2026-11-07 11:59:58');

        self::assertSame([1], $this->positions($log->read(0, 10, Filter::all())));
        self::assertSame([], $log->read(1, 10, Filter::all()));
        self::assertSame(1, $log->lastPosition());

        $counter = new Counter('all');
        $checkpoints = new InMemoryCheckpoints();
        $projector = new Projector($log, $checkpoints);
        self::assertSame(1, $projector->catchUp($counter));
        self::assertSame(1, $checkpoints->position('all'));

        // The transaction that took the position commits: the next catch-up reads it, then the entry above.
        $this->row(2, '2026-11-07 11:59:57');
        self::assertSame(2, $projector->catchUp($counter));
        self::assertSame([1, 2, 3], $counter->applied);
        self::assertSame(3, $checkpoints->position('all'));
    }

    public function testAGapIsPassedOnceTheEntryAboveItWasWrittenTheWaitAgo(): void
    {
        $clock = new Clock('2026-11-07 12:00:00 UTC');
        $log = new DbalActivityLog($this->connection, $clock);
        $this->row(1, '2026-11-07 11:00:00');
        $this->row(3, '2026-11-07 11:59:51');
        $this->row(4, '2026-11-07 11:59:59');
        $this->row(6, '2026-11-07 11:59:59');

        self::assertSame(1, $log->lastPosition());

        $clock->set('2026-11-07 12:00:00 UTC');
        self::assertSame(1, $log->lastPosition());
        $clock->set('2026-11-07 12:00:01 UTC');
        self::assertSame(4, $log->lastPosition());
        self::assertSame([1, 3, 4], $this->positions($log->read(0, 10, Filter::all())));

        $clock->set('2026-11-07 12:00:08 UTC');
        self::assertSame(4, $log->lastPosition());
        $clock->set('2026-11-07 12:00:09 UTC');
        self::assertSame(6, $log->lastPosition());
        self::assertSame([3, 4, 6], $this->positions($log->read(1, 10, Filter::all())));

        self::assertSame(6, new DbalActivityLog($this->connection, $clock, new DateInterval('PT1S'))->lastPosition());
        self::assertSame(1, new DbalActivityLog($this->connection, $clock, new DateInterval('PT1M'))->lastPosition());
    }

    public function testAGapAtTheStartOfTheJournalIsWaitedFor(): void
    {
        $clock = new Clock('2026-11-07 12:00:00 UTC');
        $log = new DbalActivityLog($this->connection, $clock);
        $this->row(2, '2026-11-07 11:59:59');

        self::assertSame(0, $log->lastPosition());
        self::assertSame([], $log->read(0, 10, Filter::all()));

        $clock->set('2026-11-07 12:00:30 UTC');
        self::assertSame(2, $log->lastPosition());

        $this->connection->executeStatement('DELETE FROM tilttek_activity_entry');
        $this->row(1, '2026-11-07 12:00:29');
        self::assertSame(1, $log->lastPosition());

        $this->connection->executeStatement('DELETE FROM tilttek_activity_entry');
        self::assertSame(0, $log->lastPosition());
    }

    public function testAGapFarBelowTheLatestEntriesIsFound(): void
    {
        $clock = new Clock('2026-11-07 12:00:00 UTC');
        $log = new DbalActivityLog($this->connection, $clock);
        $this->row(1, '2026-11-07 11:00:00');

        for ($position = 3; $position <= 1003; ++$position) {
            $this->row($position, '2026-11-07 11:59:59');
        }

        self::assertSame(1, $log->lastPosition());

        $this->row(2, '2026-11-07 11:59:59');
        self::assertSame(1003, $log->lastPosition());
    }

    public function testAnOldEntryEndsTheSearchForGaps(): void
    {
        $clock = new Clock('2026-11-07 12:00:00 UTC');
        $log = new DbalActivityLog($this->connection, $clock);
        $this->row(1, '2026-11-07 11:00:00');
        $this->row(3, '2026-11-07 11:00:00');
        $this->row(4, '2026-11-07 11:59:59');

        self::assertSame(4, $log->lastPosition());
        self::assertSame([1, 3, 4], $this->positions($log->read(0, 10, Filter::all())));
    }

    /**
     * Writes an entry at the position, as a transaction that took it and committed would.
     */
    private function row(int $position, string $recordedAt): void
    {
        $this->connection->executeStatement(
            "INSERT INTO tilttek_activity_entry (position, kind, idempotency_key, member, verb, object, quantity, context, occurred_at, recorded_at) VALUES (?, 'recorded', ?, 'member:1', 'game.played', NULL, 1, '[]', ?, ?)",
            [$position, 'k'.$position, $recordedAt, $recordedAt],
        );
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<int>
     */
    private function positions(array $entries): array
    {
        return array_map(static fn (Entry $entry): int => $entry->position, $entries);
    }

    #[Override]
    protected function log(): ActivityLog
    {
        return new DbalActivityLog($this->connection);
    }
}
