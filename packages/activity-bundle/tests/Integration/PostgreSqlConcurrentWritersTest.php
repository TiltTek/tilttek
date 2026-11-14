<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Tools\DsnParser;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\Projector;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\ActivityTables;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\DbalActivityLog;
use Tilttek\ActivityBundle\Tests\Unit\Fixture\Clock;
use Tilttek\ActivityBundle\Tests\Unit\Fixture\Counter;

/**
 * Two writers on a real PostgreSQL, read from `PHIL_TEST_POSTGRESQL_URL`, which `phil-dev services env` sets; skipped
 * without it. The tables live in a schema of their own, dropped after each test.
 */
#[CoversClass(DbalActivityLog::class)]
#[Group('postgresql')]
final class PostgreSqlConcurrentWritersTest extends TestCase
{
    private string $schema = '';

    /**
     * @var list<Connection>
     */
    private array $connections = [];

    #[Override]
    protected function tearDown(): void
    {
        if ('' !== $this->schema && [] !== $this->connections) {
            $this->connections[0]->executeStatement(\sprintf('DROP SCHEMA IF EXISTS %s CASCADE', $this->schema));
        }

        foreach ($this->connections as $connection) {
            $connection->close();
        }
    }

    public function testACatchUpWaitsForAnEarlierPositionCommittedLast(): void
    {
        [$first, $second, $reader] = $this->connections(3);
        $clock = new Clock('2026-11-07 12:00:00 UTC');
        $at = new DateTimeImmutable('2026-11-07 12:00:00 UTC');
        $counter = new Counter('all');
        $checkpoints = new InMemoryCheckpoints();
        $projector = new Projector(new DbalActivityLog($reader, $clock), $checkpoints);

        $first->beginTransaction();
        self::assertSame(1, new DbalActivityLog($first, $clock)->append($this->activity('a'), $at)->entry->position);
        self::assertSame(2, new DbalActivityLog($second, $clock)->append($this->activity('b'), $at)->entry->position);

        self::assertSame(0, $projector->catchUp($counter));
        self::assertSame(0, $checkpoints->position('all'));

        $first->commit();

        self::assertSame(2, $projector->catchUp($counter));
        self::assertSame([1, 2], $counter->applied);
        self::assertSame(2, $checkpoints->position('all'));
    }

    public function testAPositionRolledBackIsPassedOnceTheWaitIsOver(): void
    {
        [$first, $second, $reader] = $this->connections(3);
        $clock = new Clock('2026-11-07 12:00:00 UTC');
        $at = new DateTimeImmutable('2026-11-07 12:00:00 UTC');
        $counter = new Counter('all');
        $checkpoints = new InMemoryCheckpoints();
        $projector = new Projector(new DbalActivityLog($reader, $clock), $checkpoints);

        $first->beginTransaction();
        new DbalActivityLog($first, $clock)->append($this->activity('a'), $at);
        new DbalActivityLog($second, $clock)->append($this->activity('b'), $at);
        $first->rollBack();

        self::assertSame(0, $projector->catchUp($counter));

        $clock->set('2026-11-07 12:00:10 UTC');

        self::assertSame(1, $projector->catchUp($counter));
        self::assertSame([2], $counter->applied);
    }

    /**
     * @return list<Connection>
     */
    private function connections(int $count): array
    {
        $url = getenv('PHIL_TEST_POSTGRESQL_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('Set PHIL_TEST_POSTGRESQL_URL, or run phil-dev services, to test against a real server.');
        }

        $this->schema = 'tilttek_activity_'.bin2hex(random_bytes(6));

        for ($index = 0; $index < $count; ++$index) {
            $this->connections[] = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql'])->parse($url));
        }

        $this->connections[0]->executeStatement(\sprintf('CREATE SCHEMA %s', $this->schema));

        foreach ($this->connections as $connection) {
            $connection->executeStatement(\sprintf('SET search_path TO %s', $this->schema));
        }

        // A schema of the tables alone, as Database builds one for SQLite.
        $tables = ActivityTables::configure(new Schema())->getTables(); // @phpstan-ignore method.internal

        foreach ($this->connections[0]->getDatabasePlatform()->getCreateTablesSQL($tables) as $sql) {
            $this->connections[0]->executeStatement($sql);
        }

        return $this->connections;
    }

    private function activity(string $key): Activity
    {
        return Activity::of($key, 'member:1', 'game.played', new DateTimeImmutable('2026-11-07 11:00:00 UTC'));
    }
}
