<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Direction;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\ScoreStore;
use Tilttek\Leaderboards\Ties;

/**
 * The contract every score store passes: extend it with the store to test.
 */
abstract class ScoreStoreTestCase extends TestCase
{
    abstract protected function store(): ScoreStore;

    public function testAnActivityContributesOncePerBoard(): void
    {
        $store = $this->store();

        self::assertTrue($store->add(self::contribution('games', '2026-11', 'member:1', 'k1', 1, '2026-11-03 10:00')));
        self::assertFalse($store->add(self::contribution('games', '2026-11', 'member:1', 'k1', 5, '2026-11-04 10:00')));
        self::assertTrue($store->add(self::contribution('games-ever', 'ever', 'member:1', 'k1', 1, '2026-11-03 10:00')));
        self::assertTrue($store->add(self::contribution('games', '2026-11', 'member:1', 'k2', 2, '2026-11-02 10:00')));
        self::assertTrue($store->add(self::contribution('games', '2026-10', 'member:1', 'k3', 3, '2026-10-02 10:00')));
        self::assertTrue($store->add(self::contribution('games', '2026-11', 'member:2', 'k4', 4, '2026-11-02 10:00')));

        self::assertEquals([
            self::contribution('games', '2026-11', 'member:1', 'k2', 2, '2026-11-02 10:00'),
            self::contribution('games', '2026-11', 'member:1', 'k1', 1, '2026-11-03 10:00'),
        ], $store->contributions('games', '2026-11', self::member('member:1')));
        self::assertSame([], $store->contributions('games', '2026-12', self::member('member:1')));
    }

    public function testACancellationRemovesTheContributionsOfItsActivity(): void
    {
        $store = $this->store();
        $store->add(self::contribution('games', '2026-11', 'member:1', 'k1', 1, '2026-11-03 10:00'));
        $store->add(self::contribution('games-ever', 'ever', 'member:1', 'k1', 1, '2026-11-03 10:00'));
        $store->add(self::contribution('games', '2026-11', 'member:1', 'k2', 1, '2026-11-04 10:00'));

        $removed = $store->remove('k1');
        usort($removed, static fn (Contribution $a, Contribution $b): int => $a->board <=> $b->board);

        self::assertEquals([
            self::contribution('games', '2026-11', 'member:1', 'k1', 1, '2026-11-03 10:00'),
            self::contribution('games-ever', 'ever', 'member:1', 'k1', 1, '2026-11-03 10:00'),
        ], $removed);
        self::assertSame([], $store->remove('k1'));
        self::assertEquals([self::contribution('games', '2026-11', 'member:1', 'k2', 1, '2026-11-04 10:00')], $store->remove('k2'));
        self::assertTrue($store->add(self::contribution('games', '2026-11', 'member:1', 'k2', 1, '2026-11-04 10:00')));
        self::assertCount(1, $store->contributions('games', '2026-11', self::member('member:1')));
        self::assertTrue($store->add(self::contribution('games', '2026-11', 'member:1', 'k1', 1, '2026-11-03 10:00')));
    }

    public function testARebuildForgetsTheContributionsOfItsPeriod(): void
    {
        $store = $this->store();
        $store->add(self::contribution('games', '2026-10', 'member:1', 'k1', 1, '2026-10-31 23:59:59'));
        $store->add(self::contribution('games', '2026-11', 'member:1', 'k2', 1, '2026-11-01 00:00'));
        $store->add(self::contribution('games', '2026-11', 'member:2', 'k3', 1, '2026-11-02 00:00'));
        $store->add(self::contribution('games', '2026-09', 'member:2', 'k0', 1, '2026-09-30 00:00'));

        $forgotten = $store->forget(new DateTimeImmutable('2026-11-01 00:00 UTC'));
        usort($forgotten, static fn (Contribution $a, Contribution $b): int => $a->activity <=> $b->activity);

        self::assertSame(['k2', 'k3'], array_map(static fn (Contribution $contribution): string => $contribution->activity, $forgotten));
        self::assertCount(1, $store->contributions('games', '2026-10', self::member('member:1')));
        self::assertSame([], $store->contributions('games', '2026-11', self::member('member:1')));
        $all = array_map(static fn (Contribution $contribution): string => $contribution->activity, $store->forget(null));
        self::assertSame([0, 1], array_keys($all));
        sort($all);

        self::assertSame(['k0', 'k1'], $all);
        self::assertSame([], $store->contributions('games', '2026-10', self::member('member:1')));
    }

    public function testAScoreIsKeptReplacedAndDeleted(): void
    {
        $store = $this->store();

        self::assertNull($store->score('games', '2026-11', self::member('member:1')));

        $store->save(self::score('games', '2026-11', 'member:1', 3, '2026-11-03 10:00'));
        $store->save(self::score('games', '2026-11', 'member:1', 5, '2026-11-04 10:00'));
        $store->save(self::score('games', '2026-10', 'member:1', 7, '2026-10-04 10:00'));
        $store->save(self::score('games', '2026-11', 'member:2', 1, '2026-11-04 10:00'));

        self::assertEquals(self::score('games', '2026-11', 'member:1', 5, '2026-11-04 10:00'), $store->score('games', '2026-11', self::member('member:1')));
        self::assertSame(2, $store->participants('games', '2026-11'));

        $store->delete('games', '2026-11', self::member('member:1'));

        self::assertNull($store->score('games', '2026-11', self::member('member:1')));
        self::assertNotNull($store->score('games', '2026-10', self::member('member:1')));
        self::assertNotNull($store->score('games', '2026-11', self::member('member:2')));
        self::assertSame(1, $store->participants('games', '2026-11'));
    }

    public function testTheScoresAreReadInRankOrder(): void
    {
        $store = $this->filled();

        self::assertSame(
            ['member:3=9', 'member:2=7', 'member:4=7', 'member:1=7', 'member:5=2'],
            array_map(static fn (Score $score): string => $score->member->toString().'='.$score->value, $store->top('games', '2026-11', 10, self::arrival())),
        );
        self::assertSame(['member:3', 'member:2'], array_map(static fn (Score $score): string => $score->member->toString(), $store->top('games', '2026-11', 2, self::arrival())));
        self::assertSame(['member:4', 'member:5'], array_map(static fn (Score $score): string => $score->member->toString(), $store->top('games', '2026-11', 10, self::arrival(), Circle::of('member:5', 'member:4', 'member:9'))));
        self::assertEquals(self::score('games', '2026-11', 'member:3', 9, '2026-11-05 10:00'), $store->top('games', '2026-11', 1, self::arrival())[0]);
        self::assertSame([], $store->top('games', '2026-12', 10, self::arrival()));
        self::assertSame(5, $store->participants('games', '2026-11'));
        self::assertSame(2, $store->participants('games', '2026-11', Circle::of('member:5', 'member:4', 'member:9')));
    }

    public function testHowManyRankAhead(): void
    {
        $store = $this->filled();

        self::assertSame(0, $store->ahead(self::score('games', '2026-11', 'member:3', 9, '2026-11-05 10:00'), self::arrival()));
        self::assertSame(1, $store->ahead(self::score('games', '2026-11', 'member:2', 7, '2026-11-02 10:00'), self::arrival()));
        self::assertSame(2, $store->ahead(self::score('games', '2026-11', 'member:4', 7, '2026-11-02 10:00'), self::arrival()));
        self::assertSame(3, $store->ahead(self::score('games', '2026-11', 'member:1', 7, '2026-11-03 10:00'), self::arrival()));
        self::assertSame(1, $store->ahead(self::score('games', '2026-11', 'member:1', 7, '2026-11-03 10:00'), self::shared()));
        self::assertSame(4, $store->ahead(self::score('games', '2026-11', 'member:5', 2, '2026-11-01 10:00'), self::shared()));
        self::assertSame(1, $store->ahead(self::score('games', '2026-11', 'member:1', 7, '2026-11-03 10:00'), self::arrival(), Circle::of('member:1', 'member:4', 'member:5')));
        self::assertSame(0, $store->ahead(self::score('games', '2026-12', 'member:1', 0, '2026-12-03 10:00'), self::arrival()));
    }

    public function testTheTieBreaksOrderEqualScores(): void
    {
        $store = $this->store();
        $store->save(self::score('games', '2026-11', 'member:1', 7, '2026-11-01 10:00', [3, 10]));
        $store->save(self::score('games', '2026-11', 'member:2', 7, '2026-11-05 10:00', [2, 1]));
        $store->save(self::score('games', '2026-11', 'member:3', 7, '2026-11-06 10:00', [3, 20]));
        $store->save(self::score('games', '2026-11', 'member:4', 7, '2026-11-02 10:00', [3, 10]));
        $store->save(self::score('games', '2026-11', 'member:5', 8, '2026-11-07 10:00', [9, 0]));

        $byArrival = new Ranking([Direction::Ascending, Direction::Descending]);
        $shared = new Ranking([Direction::Ascending, Direction::Descending], Ties::Shared);
        $members = static fn (array $scores): array => array_map(static fn (Score $score): string => $score->member->toString(), $scores);

        self::assertSame(['member:5', 'member:2', 'member:3', 'member:1', 'member:4'], $members($store->top('games', '2026-11', 10, $byArrival)));
        self::assertSame(['member:5', 'member:2', 'member:3'], $members($store->top('games', '2026-11', 3, $shared)));
        self::assertSame(['member:2', 'member:1', 'member:4'], $members($store->top('games', '2026-11', 10, $byArrival, Circle::of('member:1', 'member:2', 'member:4'))));
        self::assertEquals(self::score('games', '2026-11', 'member:2', 7, '2026-11-05 10:00', [2, 1]), $store->top('games', '2026-11', 2, $byArrival)[1]);
        self::assertSame(4, $store->ahead(self::score('games', '2026-11', 'member:4', 7, '2026-11-02 10:00', [3, 10]), $byArrival));
        self::assertSame(3, $store->ahead(self::score('games', '2026-11', 'member:1', 7, '2026-11-01 10:00', [3, 10]), $byArrival));
        self::assertSame(3, $store->ahead(self::score('games', '2026-11', 'member:4', 7, '2026-11-02 10:00', [3, 10]), $shared));
        self::assertSame(2, $store->ahead(self::score('games', '2026-11', 'member:3', 7, '2026-11-06 10:00', [3, 20]), $shared));
        self::assertSame(1, $store->ahead(self::score('games', '2026-11', 'member:4', 7, '2026-11-02 10:00', [3, 10]), $shared, Circle::of('member:1', 'member:2', 'member:4')));
        self::assertSame(2, $store->ahead(self::score('games', '2026-11', 'member:4', 7, '2026-11-02 10:00', [3, 10]), $byArrival, Circle::of('member:1', 'member:2', 'member:4')));
    }

    public function testAMissingTieBreakIsAZero(): void
    {
        foreach ([[Direction::Descending, Direction::Descending], [Direction::Ascending, Direction::Ascending]] as $directions) {
            $store = $this->store();
            $ranking = new Ranking($directions, Ties::Shared);
            $store->save(self::score('games', '2026-11', 'member:1', 5, '2026-11-01 10:00'));
            $store->save(self::score('games', '2026-11', 'member:2', 5, '2026-11-01 10:00', [0, 0]));

            self::assertSame(0, $store->ahead(self::score('games', '2026-11', 'member:3', 5, '2026-11-02 10:00', [0, 0]), $ranking));
            self::assertSame(0, $store->ahead(self::score('games', '2026-11', 'member:3', 5, '2026-11-02 10:00'), $ranking));
        }
    }

    public function testATieBreakOnlyOrdersTheScoresEqualOnTheValue(): void
    {
        $store = $this->store();
        $store->save(self::score('games', '2026-11', 'member:1', 10, '2026-11-01 10:00', [1]));
        $store->save(self::score('games', '2026-11', 'member:2', 5, '2026-11-01 10:00', [100]));
        $store->save(self::score('games', '2026-11', 'member:3', 3, '2026-11-01 10:00', [500]));

        self::assertSame(1, $store->ahead(self::score('games', '2026-11', 'member:2', 5, '2026-11-01 10:00', [100]), new Ranking([Direction::Descending], Ties::Shared)));
    }

    public function testAnOldContributionLeavesTheWindow(): void
    {
        $store = $this->store();
        $store->add(self::contribution('regular', 'window', 'member:1', 'k1', 1, '2026-11-01 10:00', [4]));
        $store->add(self::contribution('regular@0123456789abcdef01234567', 'window', 'member:1', 'k1', 1, '2026-11-01 11:00'));
        $store->add(self::contribution('regular', 'window', 'member:1', 'k2', 1, '2026-11-03 00:00'));
        $store->add(self::contribution('regular-two', 'window', 'member:1', 'k3', 1, '2026-11-01 10:00'));
        $store->add(self::contribution('other', 'window', 'member:1', 'k4', 1, '2026-11-01 10:00'));

        $expired = $store->expire('regular', new DateTimeImmutable('2026-11-03 00:00 UTC'));
        usort($expired, static fn (Contribution $a, Contribution $b): int => $a->board <=> $b->board);

        self::assertEquals([
            self::contribution('regular', 'window', 'member:1', 'k1', 1, '2026-11-01 10:00', [4]),
            self::contribution('regular@0123456789abcdef01234567', 'window', 'member:1', 'k1', 1, '2026-11-01 11:00'),
        ], $expired);
        self::assertSame([], $store->expire('regular', new DateTimeImmutable('2026-11-03 00:00 UTC')));
        self::assertCount(1, $store->contributions('regular', 'window', self::member('member:1')));
        self::assertCount(1, $store->contributions('regular-two', 'window', self::member('member:1')));
        self::assertCount(1, $store->contributions('other', 'window', self::member('member:1')));
    }

    public function testTheTieBreaksAreKept(): void
    {
        $store = $this->store();
        $store->add(self::contribution('games', '2026-11', 'member:1', 'k1', 1, '2026-11-03 10:00', [1_000_000_000_000_000, 0]));
        $store->save(self::score('games', '2026-11', 'member:1', 1, '2026-11-03 10:00', [1_000_000_000_000_000, 0]));

        self::assertEquals([self::contribution('games', '2026-11', 'member:1', 'k1', 1, '2026-11-03 10:00', [1_000_000_000_000_000, 0])], $store->contributions('games', '2026-11', self::member('member:1')));
        self::assertEquals(self::score('games', '2026-11', 'member:1', 1, '2026-11-03 10:00', [1_000_000_000_000_000, 0]), $store->score('games', '2026-11', self::member('member:1')));
    }

    public function testTheScoresOfAMember(): void
    {
        $store = $this->store();
        $store->save(self::score('games', '2026-11', 'member:1', 3, '2026-11-03 10:00'));
        $store->save(self::score('arcade', 'ever', 'member:1', 5, '2026-11-04 10:00'));
        $store->save(self::score('games', '2026-10', 'member:1', 7, '2026-10-04 10:00'));
        $store->save(self::score('games', '2026-11', 'member:2', 1, '2026-11-04 10:00'));

        self::assertEquals([
            self::score('arcade', 'ever', 'member:1', 5, '2026-11-04 10:00'),
            self::score('games', '2026-10', 'member:1', 7, '2026-10-04 10:00'),
            self::score('games', '2026-11', 'member:1', 3, '2026-11-03 10:00'),
        ], $store->of(self::member('member:1')));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->add(self::contribution('games', '2026-11', 'member:1', 'k1', 1, '2026-11-03 10:00'));
        $store->add(self::contribution('games', '2026-11', 'member:1', 'k2', 1, '2026-11-04 10:00'));
        $store->add(self::contribution('games', '2026-11', 'member:2', 'k3', 1, '2026-11-04 10:00'));
        $store->save(self::score('games', '2026-11', 'member:1', 2, '2026-11-04 10:00'));
        $store->save(self::score('games', '2026-11', 'member:2', 1, '2026-11-04 10:00'));

        self::assertSame(3, $store->eraseMember(self::member('member:1')));
        self::assertSame([], $store->contributions('games', '2026-11', self::member('member:1')));
        self::assertSame([], $store->of(self::member('member:1')));
        self::assertCount(1, $store->contributions('games', '2026-11', self::member('member:2')));
        self::assertCount(1, $store->of(self::member('member:2')));
        self::assertSame(0, $store->eraseMember(self::member('member:1')));
    }

    /**
     * @param list<int> $tieBreaks
     */
    protected static function contribution(string $board, string $period, string $member, string $activity, int $amount, string $at, array $tieBreaks = []): Contribution
    {
        return new Contribution($board, $period, self::member($member), $activity, $amount, new DateTimeImmutable($at.' UTC'), $tieBreaks);
    }

    /**
     * @param list<int> $tieBreaks
     */
    protected static function score(string $board, string $period, string $member, int $value, string $at, array $tieBreaks = []): Score
    {
        return new Score($board, $period, self::member($member), $value, new DateTimeImmutable($at.' UTC'), $tieBreaks);
    }

    protected static function arrival(): Ranking
    {
        return new Ranking();
    }

    protected static function shared(): Ranking
    {
        return new Ranking(ties: Ties::Shared);
    }

    protected static function member(string $member): ResourceReference
    {
        return ResourceReference::fromString($member);
    }

    /**
     * A store with five scores on `games` in `2026-11` — three of them equal, two reached at the same moment —, and
     * one on another board.
     */
    private function filled(): ScoreStore
    {
        $store = $this->store();
        $store->save(self::score('games', '2026-11', 'member:1', 7, '2026-11-03 10:00'));
        $store->save(self::score('games', '2026-11', 'member:4', 7, '2026-11-02 10:00'));
        $store->save(self::score('games', '2026-11', 'member:2', 7, '2026-11-02 10:00'));
        $store->save(self::score('games', '2026-11', 'member:5', 2, '2026-11-01 10:00'));
        $store->save(self::score('games', '2026-11', 'member:3', 9, '2026-11-05 10:00'));
        $store->save(self::score('arcade', '2026-11', 'member:6', 99, '2026-11-05 10:00'));

        return $store;
    }
}
