<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Direction;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\ScoreStore;
use Tilttek\Leaderboards\Store\Valkey\ValkeyScoreStore;
use Tilttek\Leaderboards\Tests\Unit\Fixture\RespConnection;
use Tilttek\Leaderboards\Ties;

/**
 * The score store against a real Valkey, whose URL `PHIL_TEST_VALKEY_URL` gives — `redis://127.0.0.1:16379/15` —;
 * skipped without it. Each test empties the database it is given.
 */
#[CoversClass(ValkeyScoreStore::class)]
final class ValkeyScoreStoreTest extends ScoreStoreTestCase
{
    private ?RespConnection $connection = null;

    protected function store(): ScoreStore
    {
        return new ValkeyScoreStore($this->connection(), $this->boards(), 'tests');
    }

    public function testTheSortKeysFollowTheBoardsDirections(): void
    {
        $store = new ValkeyScoreStore($this->connection(), $this->boards(), 'tests');
        $score = new Score('games', '2026-11', ResourceReference::fromString('member:1'), 7, new DateTimeImmutable('1970-01-01 00:00:01.000002 UTC'), [3, 10]);

        self::assertSame('099999999999999300000000000000030999999999999990062135596801000002member:1', $store->sortKey($score));
        self::assertSame('0999999999999993062135596801000002member:1', $store->sortKey(new Score('arcade', '2026-11', ResourceReference::fromString('member:1'), 7, new DateTimeImmutable('1970-01-01 00:00:01.000002 UTC'), [3, 10])));
        self::assertSame('099999999999999300000000000000030999999999999990062135596801000002member:1', $store->sortKey(new Score('games@abc', '2026-11', ResourceReference::fromString('member:1'), 7, new DateTimeImmutable('1970-01-01 00:00:01.000002 UTC'), [3, 10])));
    }

    public function testARankingInTheBoardsDirectionsIsReadFromTheSortedSet(): void
    {
        $store = $this->store();
        $ranking = new Ranking([Direction::Ascending, Direction::Descending]);
        $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:1'), 7, new DateTimeImmutable('2026-11-01 UTC'), [3, 10]));
        $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:2'), 7, new DateTimeImmutable('2026-11-01 UTC'), [2, 10]));
        $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:3'), 7, new DateTimeImmutable('2026-11-01 UTC'), [3, 10]));
        $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:2'), 9, new DateTimeImmutable('2026-11-02 UTC'), [9, 9]));

        self::assertSame(['member:2', 'member:1', 'member:3'], array_map(static fn (Score $score): string => $score->member->toString(), $store->top('games', '2026-11', 3, $ranking)));
        self::assertSame(3, $store->participants('games', '2026-11'));
        self::assertSame(2, $store->ahead(new Score('games', '2026-11', ResourceReference::fromString('member:3'), 7, new DateTimeImmutable('2026-11-01 UTC'), [3, 10]), $ranking));
        self::assertSame(1, $store->ahead(new Score('games', '2026-11', ResourceReference::fromString('member:3'), 7, new DateTimeImmutable('2026-11-01 UTC'), [3, 10]), new Ranking([Direction::Ascending, Direction::Descending], Ties::Shared)));
        self::assertSame(1, $store->ahead(new Score('games', '2026-11', ResourceReference::fromString('member:9'), 8, new DateTimeImmutable('2026-11-01 UTC'), [0, 0]), $ranking));

        $store->delete('games', '2026-11', ResourceReference::fromString('member:2'));
        $store->delete('games', '2026-11', ResourceReference::fromString('member:2'));

        self::assertSame(['member:1', 'member:3'], array_map(static fn (Score $score): string => $score->member->toString(), $store->top('games', '2026-11', 3, $ranking)));
        self::assertSame(['member:3'], array_map(static fn (Score $score): string => $score->member->toString(), $store->top('games', '2026-11', 3, $ranking, Circle::of('member:3'))));
    }

    public function testManyContributionsAreRemovedByBatches(): void
    {
        $store = new ValkeyScoreStore($this->connection(), $this->boards(), 'tests', 2);

        for ($index = 0; $index < 5; ++$index) {
            $store->add(new Contribution('games', '2026-11', ResourceReference::fromString('member:1'), 'k'.$index, 1, new DateTimeImmutable('2026-11-01 UTC')));
        }

        self::assertCount(5, $store->forget(null));
        self::assertSame([], $store->contributions('games', '2026-11', ResourceReference::fromString('member:1')));
    }

    public function testAPrefixIsAName(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('"Tests}" is not a prefix name');

        new ValkeyScoreStore($this->connection(), $this->boards(), 'Tests}');
    }

    public function testABatchIsBounded(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('A script removes 1 to 10000 contributions at a time; 0 is not a batch.');

        new ValkeyScoreStore($this->connection(), $this->boards(), 'tests', 0);
    }

    private function connection(): RespConnection
    {
        if ($this->connection instanceof RespConnection) {
            return $this->connection;
        }

        $url = getenv('PHIL_TEST_VALKEY_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('No Valkey to test against: PHIL_TEST_VALKEY_URL is not set.');
        }

        $this->connection = new RespConnection($url);
        $this->connection->execute('FLUSHDB');

        return $this->connection;
    }

    private function boards(): Boards
    {
        return Boards::fromArray([
            'games' => ['measure' => 'total', 'verbs' => ['game.played'], 'tie_breaks' => [['measure' => 'lowest', 'order' => 'asc'], ['measure' => 'highest']]],
            'arcade' => ['measure' => 'best', 'verbs' => ['arcade.scored']],
        ]);
    }
}
