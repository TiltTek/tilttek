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
use Tilttek\Leaderboards\Measure;
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

    public function testARankingInOtherDirectionsIsOrderedFromTheScores(): void
    {
        $store = $this->store();
        $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:1'), 7, new DateTimeImmutable('2026-11-01 UTC'), [2, 10]));
        $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:2'), 7, new DateTimeImmutable('2026-11-02 UTC'), [3, 10]));
        $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:3'), 5, new DateTimeImmutable('2026-11-03 UTC'), [9, 9]));

        $members = static fn (array $scores): array => array_map(static fn (Score $score): string => $score->member->toString(), $scores);

        self::assertSame(['member:1', 'member:2', 'member:3'], $members($store->top('games', '2026-11', 3, new Ranking([Direction::Ascending, Direction::Descending]))));
        self::assertSame(['member:2', 'member:1', 'member:3'], $members($store->top('games', '2026-11', 3, new Ranking([Direction::Descending, Direction::Descending]))));
        self::assertSame(['member:2'], $members($store->top('games', '2026-11', 1, new Ranking([Direction::Descending, Direction::Descending]))));
        self::assertSame(0, $store->ahead(new Score('games', '2026-11', ResourceReference::fromString('member:2'), 7, new DateTimeImmutable('2026-11-02 UTC'), [3, 10]), new Ranking([Direction::Descending, Direction::Descending])));
        self::assertSame(1, $store->ahead(new Score('games', '2026-11', ResourceReference::fromString('member:2'), 7, new DateTimeImmutable('2026-11-02 UTC'), [3, 10]), new Ranking([Direction::Ascending, Direction::Descending])));
    }

    public function testTheScoresOfAMemberAreInBoardThenPeriodOrder(): void
    {
        $store = $this->store();
        $saved = [['games', '2026-12'], ['arcade', '2026-11'], ['games', '2026-10'], ['arcade', 'ever'], ['games', '2026-11'], ['arcade', '2026-10']];

        foreach ($saved as [$board, $period]) {
            $store->save(new Score($board, $period, ResourceReference::fromString('member:1'), 1, new DateTimeImmutable('2026-11-01 UTC')));
        }

        self::assertSame(
            ['arcade 2026-10', 'arcade 2026-11', 'arcade ever', 'games 2026-10', 'games 2026-11', 'games 2026-12'],
            array_map(static fn (Score $score): string => $score->board.' '.$score->period, $store->of(ResourceReference::fromString('member:1'))),
        );
    }

    public function testTheScriptsLeaveNoKeyBehind(): void
    {
        $store = $this->store();
        $connection = $this->connection();

        $store->add(new Contribution('games', '2026-11', ResourceReference::fromString('member:1'), 'k1', 1, new DateTimeImmutable('2026-11-01 UTC')));
        $store->add(new Contribution('games@abc', '2026-11', ResourceReference::fromString('member:2'), 'k1', 1, new DateTimeImmutable('2026-11-02 UTC')));
        $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:1'), 1, new DateTimeImmutable('2026-11-01 UTC')));
        $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:1'), 2, new DateTimeImmutable('2026-11-02 UTC')));
        $store->keep(new Score('arcade', '2026-11', ResourceReference::fromString('member:2'), 3, new DateTimeImmutable('2026-11-01 UTC')), Measure::Highest, new Ranking());
        $store->keep(new Score('arcade', '2026-11', ResourceReference::fromString('member:2'), 4, new DateTimeImmutable('2026-11-02 UTC')), Measure::Highest, new Ranking());

        $keys = $connection->execute('KEYS', '*');
        self::assertIsArray($keys);
        sort($keys);
        self::assertSame([
            '{tests}:activity:["k1"]',
            '{tests}:board:["games"]',
            '{tests}:contribution',
            '{tests}:member-score:["member:1"]',
            '{tests}:member-score:["member:2"]',
            '{tests}:member:["member:1"]',
            '{tests}:member:["member:2"]',
            '{tests}:of:["games","2026-11","member:1"]',
            '{tests}:of:["games@abc","2026-11","member:2"]',
            '{tests}:rank:["arcade","2026-11"]',
            '{tests}:rank:["games","2026-11"]',
            '{tests}:score',
            '{tests}:time',
        ], $keys);
        self::assertSame(1, $connection->execute('ZCARD', '{tests}:rank:["games","2026-11"]'));
        self::assertSame(1, $connection->execute('ZCARD', '{tests}:rank:["arcade","2026-11"]'));

        self::assertCount(2, $store->remove('k1'));
        $store->delete('games', '2026-11', ResourceReference::fromString('member:1'));
        $store->delete('arcade', '2026-11', ResourceReference::fromString('member:2'));

        self::assertSame(0, $connection->execute('DBSIZE'));
    }

    public function testAnErasureCountsWhatItRemoved(): void
    {
        $store = $this->store();
        $store->add(new Contribution('games', '2026-11', ResourceReference::fromString('member:1'), 'k1', 1, new DateTimeImmutable('2026-11-01 UTC')));
        $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:1'), 1, new DateTimeImmutable('2026-11-01 UTC')));
        $store->save(new Score('games', '2026-10', ResourceReference::fromString('member:1'), 1, new DateTimeImmutable('2026-10-01 UTC')));
        $store->save(new Score('arcade', 'ever', ResourceReference::fromString('member:1'), 1, new DateTimeImmutable('2026-10-01 UTC')));

        self::assertSame(4, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertSame(0, $this->connection()->execute('DBSIZE'));
    }

    public function testAnIndexEntryWithoutItsScoreIsErasedButNotCounted(): void
    {
        $store = $this->store();
        $connection = $this->connection();
        $store->save(new Score('arcade', 'ever', ResourceReference::fromString('member:1'), 1, new DateTimeImmutable('2026-10-01 UTC')));
        $store->save(new Score('arcade', 'ever', ResourceReference::fromString('member:2'), 1, new DateTimeImmutable('2026-10-01 UTC')));
        // A partial write from elsewhere, a restore or a hand edit: the index names a score the hash no longer holds.
        $connection->execute('SADD', '{tests}:member-score:["member:1"]', '["games","2026-11","member:1"]');

        self::assertSame(1, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertSame(0, $connection->execute('EXISTS', '{tests}:member-score:["member:1"]'));
        self::assertSame(['["arcade","ever","member:2"]'], $connection->execute('SMEMBERS', '{tests}:member-score:["member:2"]'));
        self::assertSame(1, $connection->execute('ZCARD', '{tests}:rank:["arcade","ever"]'));
        self::assertSame(0, $store->eraseMember(ResourceReference::fromString('member:1')));
    }

    public function testDeletingAScoreNotHeldTakesItsEntryOutOfTheIndex(): void
    {
        $store = $this->store();
        $connection = $this->connection();
        $connection->execute('SADD', '{tests}:member-score:["member:1"]', '["games","2026-11","member:1"]', '["games","2026-10","member:1"]');

        $store->delete('games', '2026-11', ResourceReference::fromString('member:1'));

        self::assertSame(['["games","2026-10","member:1"]'], $connection->execute('SMEMBERS', '{tests}:member-score:["member:1"]'));
        self::assertSame(1, $connection->execute('DBSIZE'));
    }

    public function testTheLatestScoreKeepsOnlyALaterMomentOrAnotherValue(): void
    {
        $store = $this->store();
        $member = ResourceReference::fromString('member:1');
        $at = static fn (string $moment): DateTimeImmutable => new DateTimeImmutable($moment.' UTC');

        self::assertTrue($store->keep(new Score('games', '2026-11', $member, 5, $at('2026-11-05 10:00'), [3, 10]), Measure::Latest, new Ranking()));
        self::assertFalse($store->keep(new Score('games', '2026-11', $member, 5, $at('2026-11-06 10:00'), [3, 10]), Measure::Latest, new Ranking()));
        self::assertTrue($store->keep(new Score('games', '2026-11', $member, 5, $at('2026-11-06 10:00'), [3, 11]), Measure::Latest, new Ranking()));
        self::assertFalse($store->keep(new Score('games', '2026-11', $member, 6, $at('2026-11-06 09:59:59.999999'), [3, 11]), Measure::Latest, new Ranking()));
        self::assertTrue($store->keep(new Score('games', '2026-11', $member, 6, $at('2026-11-06 10:00'), [3, 11]), Measure::Latest, new Ranking()));
        self::assertEquals(new Score('games', '2026-11', $member, 6, $at('2026-11-06 10:00'), [3, 11]), $store->score('games', '2026-11', $member));
        self::assertSame(1, $store->participants('games', '2026-11'));
    }

    public function testTheHighestScoreKeepsOnlyABetterOne(): void
    {
        $store = $this->store();
        $member = ResourceReference::fromString('member:1');
        $at = new DateTimeImmutable('2026-11-05 10:00 UTC');
        $ranking = new Ranking([Direction::Ascending, Direction::Descending]);

        self::assertTrue($store->keep(new Score('games', '2026-11', $member, 5, $at, [3, 10]), Measure::Highest, $ranking));
        self::assertFalse($store->keep(new Score('games', '2026-11', $member, 5, $at, [3, 10]), Measure::Highest, $ranking));
        self::assertFalse($store->keep(new Score('games', '2026-11', $member, 5, $at->modify('-1 day'), [3, 10]), Measure::Highest, $ranking));
        self::assertTrue($store->keep(new Score('games', '2026-11', $member, 5, $at, [2, 0]), Measure::Highest, $ranking));
        self::assertFalse($store->keep(new Score('games', '2026-11', $member, 4, $at, [0, 99]), Measure::Highest, $ranking));
        self::assertTrue($store->keep(new Score('games', '2026-11', $member, 6, $at, [9, 0]), Measure::Highest, $ranking));
        self::assertEquals(new Score('games', '2026-11', $member, 6, $at, [9, 0]), $store->score('games', '2026-11', $member));
        self::assertSame(1, $store->participants('games', '2026-11'));
    }

    public function testAWindowExpiresBeforeTheMomentAndARebuildForgetsFromIt(): void
    {
        $store = $this->store();
        $member = ResourceReference::fromString('member:1');
        $store->add(new Contribution('games', '2026-11', $member, 'k1', 1, new DateTimeImmutable('2026-11-03 09:59:59.999999 UTC')));
        $store->add(new Contribution('games', '2026-11', $member, 'k2', 1, new DateTimeImmutable('2026-11-03 10:00 UTC')));
        $store->add(new Contribution('games', '2026-11', $member, 'k3', 1, new DateTimeImmutable('2026-11-03 10:00:00.000001 UTC')));

        self::assertSame(['k1'], array_map(static fn (Contribution $contribution): string => $contribution->activity, $store->expire('games', new DateTimeImmutable('2026-11-03 10:00 UTC'))));
        self::assertSame(['k3'], array_map(static fn (Contribution $contribution): string => $contribution->activity, $store->forget(new DateTimeImmutable('2026-11-03 10:00:00.000001 UTC'))));
        self::assertSame(['k2'], array_map(static fn (Contribution $contribution): string => $contribution->activity, $store->contributions('games', '2026-11', $member)));
    }

    public function testTheBatchBoundsAreBatches(): void
    {
        $one = new ValkeyScoreStore($this->connection(), $this->boards(), 'tests', 1);

        for ($index = 0; $index < 3; ++$index) {
            $one->add(new Contribution('games', '2026-11', ResourceReference::fromString('member:1'), 'k'.$index, 1, new DateTimeImmutable('2026-11-01 UTC')));
        }

        self::assertCount(3, $one->forget(null));

        $most = new ValkeyScoreStore($this->connection(), $this->boards(), 'tests', 10_000);
        $most->add(new Contribution('games', '2026-11', ResourceReference::fromString('member:1'), 'k1', 1, new DateTimeImmutable('2026-11-01 UTC')));

        self::assertCount(1, $most->forget(null));
        self::assertSame(0, $this->connection()->execute('DBSIZE'));
    }

    public function testABatchIsAtMostTenThousand(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('A script removes 1 to 10000 contributions at a time; 10001 is not a batch.');

        new ValkeyScoreStore($this->connection(), $this->boards(), 'tests', 10_001);
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
