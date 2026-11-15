<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Placing;
use Tilttek\Leaderboards\Snapshot;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalSnapshotStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;

/**
 * What the contracts do not show of a real server: the tables and the index of the ranking as the server reads them
 * back, a moment of another zone kept in UTC, and a row already there ignored without aborting the transaction around
 * it.
 */
abstract class ServerTablesTestCase extends TestCase
{
    public function testTheServerHasTheTablesAndTheIndexOfTheRanking(): void
    {
        $manager = $this->server()->createSchemaManager();
        $index = array_find($manager->introspectTableIndexesByUnquotedName(LeaderboardsTables::SCORES), static fn (Index $index): bool => 'tilttek_leaderboards_score_rank' === strtolower($index->getObjectName()->getIdentifier()->getValue()));

        self::assertNotNull($index);
        self::assertSame(['board', 'period', 'score', 'tie_break_1', 'tie_break_2', 'reached_at'], array_map(static fn (IndexedColumn $column): string => strtolower($column->getColumnName()->getIdentifier()->getValue()), $index->getIndexedColumns()));
        self::assertTrue($manager->tableExists(LeaderboardsTables::LEAGUE_WEEKS));
    }

    public function testAMomentOfAnotherZoneIsKeptInUtc(): void
    {
        $store = new DbalScoreStore($this->server());
        $member = ResourceReference::fromString('member:1');
        $store->add(new Contribution('games', 'ever', $member, 'k1', 1, new DateTimeImmutable('2026-11-07 10:00:00 America/Toronto')));

        self::assertStringStartsWith('2026-11-07 15:00:00', Rows::text($this->server()->fetchOne('SELECT occurred_at FROM '.LeaderboardsTables::CONTRIBUTIONS)));
        self::assertSame('2026-11-07T15:00:00+00:00', $store->contributions('games', 'ever', $member)[0]->occurredAt->format(\DATE_ATOM));
        self::assertCount(1, $store->forget(new DateTimeImmutable('2026-11-07 09:30:00 America/Toronto')));
    }

    public function testARowAlreadyThereIsIgnoredWithoutAbortingTheTransaction(): void
    {
        $snapshots = new DbalSnapshotStore($this->server());
        $at = new DateTimeImmutable('2026-12-01 00:00:00 UTC');
        $snapshot = new Snapshot('games', '2026-11', $at, 1, [new Placing('games', '2026-11', ResourceReference::fromString('member:1'), 1, 4, $at)]);

        $this->server()->transactional(static function () use ($snapshots, $snapshot, $at): void {
            self::assertTrue($snapshots->save($snapshot));
            self::assertFalse($snapshots->save($snapshot));
            self::assertTrue($snapshots->save(new Snapshot('games', '2026-10', $at, 0, [])));
        });

        self::assertSame(2, Rows::integer($this->server()->fetchOne('SELECT COUNT(*) FROM '.LeaderboardsTables::SNAPSHOTS)));
        self::assertSame(1, Rows::integer($this->server()->fetchOne('SELECT COUNT(*) FROM '.LeaderboardsTables::PLACINGS)));
    }

    abstract protected function server(): Connection;
}
