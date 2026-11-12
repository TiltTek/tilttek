<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Exception\PeriodNotOver;
use Tilttek\Leaderboards\Exception\UnknownBoard;
use Tilttek\Leaderboards\Exception\UnknownPeriod;
use Tilttek\Leaderboards\Leaderboards;
use Tilttek\Leaderboards\Placing;
use Tilttek\Leaderboards\Row;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Snapshot;
use Tilttek\Leaderboards\Standing;
use Tilttek\Leaderboards\Store\SnapshotStore;
use Tilttek\Leaderboards\Tests\Unit\Fixture\Kit;

#[CoversClass(Leaderboards::class)]
#[CoversClass(Snapshot::class)]
final class LeaderboardsTest extends TestCase
{
    public function testTheBoardsAndTheirPeriods(): void
    {
        $kit = $this->kit();

        self::assertSame($kit->boards, $kit->leaderboards->boards());
        self::assertSame('2026-11', $kit->leaderboards->period('games')->code);
        self::assertSame('2026-10', $kit->leaderboards->period('games', '2026-10')->code);
        self::assertSame('ever', $kit->leaderboards->period('points')->code);
    }

    public function testAnUnknownPeriod(): void
    {
        $this->expectException(UnknownPeriod::class);
        $this->expectExceptionMessageIsOrContains('The board "games" has no period coded "2026-13".');

        $this->kit()->leaderboards->period('games', '2026-13');
    }

    public function testNoPeriodIsUnderWayBetweenTwoSeasons(): void
    {
        $this->expectException(UnknownPeriod::class);
        $this->expectExceptionMessageIsOrContains('The board "summer" is between two seasons: no period is under way.');

        $this->kit()->leaderboards->period('summer');
    }

    public function testAnUnknownBoard(): void
    {
        $this->expectException(UnknownBoard::class);

        $this->kit()->leaderboards->standing('nothing');
    }

    public function testNobodyIsShownWithoutChoosingIt(): void
    {
        $kit = $this->played();
        $kit->leaderboards->appear($this->member('member:3'), Appearance::named());
        $kit->leaderboards->appear($this->member('member:2'), Appearance::pseudonym('Gris'));

        $anonymous = $kit->leaderboards->standing('games');

        self::assertSame(['1 9 member:3 - ', '2 7 - Gris ', '3 7 - - ', '4 7 - - ', '5 2 - - '], $this->lines($anonymous->rows));
        self::assertNull($anonymous->own);
        self::assertSame(5, $anonymous->participants);
        self::assertSame('2026-11', $anonymous->period->code);
        self::assertSame('games', $anonymous->board->name);
        self::assertNull($anonymous->frozenAt);

        $own = $kit->leaderboards->standing('games', $this->member('member:2'));

        self::assertSame(['1 9 member:3 - ', '2 7 member:2 Gris self', '3 7 - - ', '4 7 - - ', '5 2 - - '], $this->lines($own->rows));
        self::assertSame('2 7 member:2 Gris self', $this->lines([$own->own ?? new Row(0, 0)])[0]);
    }

    public function testTheViewerSeesTheirOwnLineBelowTheFirstOnes(): void
    {
        $kit = $this->played();

        $standing = $kit->leaderboards->standing('games', $this->member('member:5'), limit: 2);

        self::assertSame(['1 9 - - ', '2 7 - - '], $this->lines($standing->rows));
        self::assertSame('5 2 member:5 - self', $this->lines([$standing->own ?? new Row(0, 0)])[0]);
        self::assertNull($kit->leaderboards->standing('games', $this->member('member:9'))->own);
    }

    public function testSharedTiesSkipRanks(): void
    {
        $kit = $this->played();

        self::assertSame(['1 9 - - ', '2 7 - - ', '2 7 - - ', '2 7 member:1 - self', '5 2 - - '], $this->lines($kit->leaderboards->standing('shared', $this->member('member:1'))->rows));
        self::assertSame(2, $kit->leaderboards->rank('shared', $this->member('member:1'))?->rank);
        self::assertSame(4, $kit->leaderboards->rank('games', $this->member('member:1'))?->rank);
        self::assertSame(['1 3 - - ', '1 3 - - '], $this->lines($kit->leaderboards->standing('shared', period: '2026-10')->rows));
    }

    public function testARankingAmongACircle(): void
    {
        $kit = $this->played();
        $circle = Circle::of('member:1', 'member:5', 'member:9');

        $standing = $kit->leaderboards->standing('games', $this->member('member:1'), among: $circle);

        self::assertSame(['1 7 member:1 - self', '2 2 - - '], $this->lines($standing->rows));
        self::assertSame(1, $standing->own?->rank);
        self::assertSame(2, $standing->participants);
        self::assertNull($kit->leaderboards->rank('games', $this->member('member:3'), among: $circle));
        self::assertNull($kit->leaderboards->rank('games', $this->member('member:9')));
        self::assertSame(1, $kit->leaderboards->rank('games', $this->member('member:1'), '2026-11', $circle)?->rank);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedLimits(): iterable
    {
        yield 'no line' => [0];
        yield 'too many lines' => [101];
    }

    #[DataProvider('refusedLimits')]
    public function testAStandingShowsOneToAHundredLines(int $limit): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A standing shows 1 to 100 lines; %d is not one.', $limit));

        $this->played()->leaderboards->standing('games', limit: $limit);
    }

    public function testTheLimitsOfAStanding(): void
    {
        $kit = $this->played();

        self::assertCount(1, $kit->leaderboards->standing('games', limit: 1)->rows);
        self::assertCount(5, $kit->leaderboards->standing('games', limit: 100)->rows);
    }

    public function testAStandingAndASnapshotShowTenLinesByDefault(): void
    {
        $kit = $this->kit();

        foreach (range(1, 11) as $member) {
            $kit->record('k'.$member, 'member:'.$member, 'big.played', '2025-10-02 10:00', $member);
        }

        $kit->leaderboards->snapshot('big', '2025');

        self::assertCount(10, $kit->leaderboards->standing('big', period: '2025')->rows);
        self::assertCount(10, $kit->leaderboards->frozen('big', '2025')->rows ?? []);
        self::assertCount(11, $kit->leaderboards->frozen('big', '2025', limit: 11)->rows ?? []);
    }

    public function testASnapshotNeedsNoObserver(): void
    {
        $kit = $this->played();
        $kit->clock->set('2026-12-01 05:00 UTC');

        self::assertCount(3, new Leaderboards($kit->boards, $kit->scores, $kit->snapshots, $kit->appearances, $kit->clock)->snapshot('games', '2026-11')->placings);
        self::assertNotNull($kit->snapshots->find('games', '2026-11'));
    }

    public function testTheLatestValueReported(): void
    {
        $kit = $this->kit();
        $member = $this->member('member:1');

        self::assertTrue($kit->leaderboards->report('level', $member, 3, $this->at('2026-11-05 10:00')));
        self::assertFalse($kit->leaderboards->report('level', $member, 3, $this->at('2026-11-06 10:00')));
        self::assertFalse($kit->leaderboards->report('level', $member, 5, $this->at('2026-11-04 10:00')));
        self::assertSame('3 2026-11-05 10:00', $this->held($kit, 'level', 'ever'));
        self::assertTrue($kit->leaderboards->report('level', $member, 2, $this->at('2026-11-05 10:00')));
        self::assertTrue($kit->leaderboards->report('level', $member, 4));
        self::assertSame('4 2026-11-07 12:00', $this->held($kit, 'level', 'ever'));
    }

    public function testTheHighestValueReported(): void
    {
        $kit = $this->kit();
        $member = $this->member('member:1');

        self::assertTrue($kit->leaderboards->report('streak', $member, 0));
        self::assertTrue($kit->leaderboards->report('streak', $member, 5, $this->at('2026-11-05 10:00')));
        self::assertFalse($kit->leaderboards->report('streak', $member, 5, $this->at('2026-11-06 10:00')));
        self::assertFalse($kit->leaderboards->report('streak', $member, 4, $this->at('2026-11-06 10:00')));
        self::assertTrue($kit->leaderboards->report('streak', $member, 6, $this->at('2026-11-03 10:00')));
        self::assertSame('6 2026-11-03 10:00', $this->held($kit, 'streak', '2026-11'));
        self::assertFalse($kit->leaderboards->report('summer', $member, 6));
        self::assertTrue($kit->leaderboards->report('summer', $member, 6, $this->at('2026-07-01 10:00')));
    }

    public function testNothingIsReportedToABoardThatReadsTheJournal(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The board "games" reads its measure from the journal: nothing is reported to it.');

        $this->kit()->leaderboards->report('games', $this->member('member:1'), 3);
    }

    public function testAReportIsAScore(): void
    {
        $this->expectException(InvalidLeaderboard::class);

        $this->kit()->leaderboards->report('level', $this->member('member:1'), -1);
    }

    public function testAMemberChoosesHowTheyAppear(): void
    {
        $kit = $this->kit();
        $member = $this->member('member:1');

        self::assertEquals(Appearance::hidden(), $kit->leaderboards->appearance($member));

        $kit->leaderboards->appear($member, Appearance::pseudonym('Gris'));

        self::assertEquals(Appearance::pseudonym('Gris'), $kit->leaderboards->appearance($member));
    }

    public function testASnapshotFreezesTheEndOfAPeriodOnce(): void
    {
        $kit = $this->played();
        $kit->record('k9', 'member:6', 'game.played', '2026-10-15 10:00');
        $kit->clock->set('2026-12-01 05:00 UTC');

        $snapshot = $kit->leaderboards->snapshot('shared', '2026-11');

        self::assertSame(['shared', '2026-11', '2026-12-01 05:00', 5], [$snapshot->board, $snapshot->period, $snapshot->takenAt->format('Y-m-d H:i'), $snapshot->participants]);
        self::assertEquals(new Placing('shared', '2026-11', $this->member('member:3'), 1, 9, $this->at('2026-11-05 10:00')), $snapshot->placings[0]);
        self::assertSame(['snapshot shared 2026-11 5 [1:member:3=9 2:member:2=7 2:member:4=7]'], $kit->recorder->told);

        $kit->clock->set('2026-12-02 05:00 UTC');

        self::assertEquals($snapshot, $kit->leaderboards->snapshot('shared', '2026-11'));
        self::assertCount(1, $kit->recorder->told);
        self::assertSame('snapshot games 2026-10 3 [1:member:1=3 2:member:2=3 3:member:6=1]', $this->snapshotLine($kit->leaderboards->snapshot('games', '2026-10')));
    }

    public function testAPeriodUnderWayIsNotFrozen(): void
    {
        $kit = $this->played();
        $kit->clock->set('2026-12-01 04:59:59 UTC');

        $this->expectException(PeriodNotOver::class);
        $this->expectExceptionMessageIsOrContains('The period "2026-11" of the board "games" is not over: it cannot be frozen yet.');

        $kit->leaderboards->snapshot('games', '2026-11');
    }

    public function testEverIsNeverFrozen(): void
    {
        $this->expectException(PeriodNotOver::class);

        $this->kit()->leaderboards->snapshot('points', 'ever');
    }

    public function testASnapshotTakenElsewhereIsNotToldAgain(): void
    {
        $kit = $this->played();
        $kit->clock->set('2026-12-01 05:00 UTC');

        $snapshots = new class implements SnapshotStore {
            #[Override]
            public function save(Snapshot $snapshot): bool
            {
                return false;
            }

            #[Override]
            public function find(string $board, string $period): ?Snapshot
            {
                return null;
            }

            #[Override]
            public function placings(ResourceReference $member): array
            {
                return [];
            }

            #[Override]
            public function eraseMember(ResourceReference $member): int
            {
                return 0;
            }
        };
        $leaderboards = new Leaderboards($kit->boards, $kit->scores, $snapshots, $kit->appearances, $kit->clock, $kit->recorder);

        self::assertCount(3, $leaderboards->snapshot('games', '2026-11')->placings);
        self::assertSame([], $kit->recorder->told);
    }

    public function testTheSnapshotsDue(): void
    {
        $kit = $this->played();
        $kit->clock->set('2026-12-01 05:00 UTC');
        $kit->leaderboards->snapshot('games', '2026-11');

        self::assertSame(['big 2025', 'shared 2026-11', 'streak 2026-11', 'summer s'], $this->due($kit));
        self::assertCount(5, $kit->recorder->told);
        self::assertSame([], $this->due($kit));

        $kit->clock->set('2026-09-22 04:00 UTC');

        self::assertSame(['games 2026-08', 'shared 2026-08', 'streak 2026-08'], $this->due($kit));
    }

    public function testASnapshotAsTheViewerSeesIt(): void
    {
        $kit = $this->played();
        $kit->clock->set('2026-12-01 05:00 UTC');
        $kit->leaderboards->snapshot('games', '2026-11');
        $kit->leaderboards->appear($this->member('member:3'), Appearance::named());

        $frozen = $kit->leaderboards->frozen('games', '2026-11', $this->member('member:4'), 2);

        self::assertInstanceOf(Standing::class, $frozen);
        self::assertSame(['1 9 member:3 - ', '2 7 - - '], $this->lines($frozen->rows));
        self::assertSame('3 7 member:4 - self', $this->lines([$frozen->own ?? new Row(0, 0)])[0]);
        self::assertSame([5, '2026-12-01 05:00', '2026-11', 'games'], [$frozen->participants, $frozen->frozenAt?->format('Y-m-d H:i'), $frozen->period->code, $frozen->board->name]);
        self::assertNull($kit->leaderboards->frozen('games', '2026-11', $this->member('member:5'))?->own);
        self::assertNull($kit->leaderboards->frozen('games', '2026-11')?->own);
        self::assertCount(3, $kit->leaderboards->frozen('games', '2026-11', limit: 1000)->rows ?? []);
        self::assertCount(1, $kit->leaderboards->frozen('games', '2026-11', limit: 1)->rows ?? []);
        self::assertNull($kit->leaderboards->frozen('games', '2026-10'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedSnapshotLimits(): iterable
    {
        yield 'no line' => [0];
        yield 'too many lines' => [1001];
    }

    #[DataProvider('refusedSnapshotLimits')]
    public function testASnapshotShowsOneToAThousandLines(int $limit): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A snapshot shows 1 to 1000 lines; %d is not one.', $limit));

        $this->kit()->leaderboards->frozen('games', '2026-10', limit: $limit);
    }

    public function testAMemberIsExportedAndErased(): void
    {
        $kit = $this->played();
        $member = $this->member('member:2');
        $kit->clock->set('2026-12-01 05:00 UTC');
        $kit->leaderboards->snapshot('games', '2026-11');
        $kit->leaderboards->appear($member, Appearance::pseudonym('Gris'));

        self::assertSame([
            'appearance' => ['visibility' => 'pseudonym', 'pseudonym' => 'Gris'],
            'scores' => [
                ['board' => 'games', 'period' => '2026-10', 'value' => 3, 'tie_breaks' => [], 'reached_at' => '2026-10-02T10:00:00+00:00'],
                ['board' => 'games', 'period' => '2026-11', 'value' => 7, 'tie_breaks' => [], 'reached_at' => '2026-11-02T10:00:00+00:00'],
                ['board' => 'points', 'period' => 'ever', 'value' => 10, 'tie_breaks' => [], 'reached_at' => '2026-11-02T10:00:00+00:00'],
                ['board' => 'shared', 'period' => '2026-10', 'value' => 3, 'tie_breaks' => [], 'reached_at' => '2026-10-02T10:00:00+00:00'],
                ['board' => 'shared', 'period' => '2026-11', 'value' => 7, 'tie_breaks' => [], 'reached_at' => '2026-11-02T10:00:00+00:00'],
            ],
            'placings' => [['board' => 'games', 'period' => '2026-11', 'rank' => 2, 'value' => 7, 'reached_at' => '2026-11-02T10:00:00+00:00']],
            'leagues' => ['tiers' => [], 'memberships' => []],
        ], $kit->leaderboards->export($member));

        $erasure = $kit->leaderboards->erase($member);

        self::assertSame([11, 1, 1, 0], [$erasure->scores, $erasure->placings, $erasure->appearances, $erasure->leagues]);
        self::assertSame(['appearance' => ['visibility' => 'hidden', 'pseudonym' => null], 'scores' => [], 'placings' => [], 'leagues' => ['tiers' => [], 'memberships' => []]], $kit->leaderboards->export($member));
    }

    private function kit(): Kit
    {
        return new Kit(Boards::fromArray([
            'games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month', 'timezone' => 'America/Toronto', 'snapshot_size' => 3],
            'shared' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month', 'timezone' => 'America/Toronto', 'ties' => 'shared', 'snapshot_size' => 3],
            'points' => ['measure' => 'total', 'verbs' => ['game.played']],
            'big' => ['measure' => 'total', 'verbs' => ['big.played'], 'period' => 'year'],
            'level' => ['measure' => 'latest'],
            'streak' => ['measure' => 'highest', 'period' => 'month'],
            'summer' => ['measure' => 'highest', 'period' => 'season', 'seasons' => [['code' => 's', 'start' => '2026-06-21', 'end' => '2026-09-22']]],
        ]));
    }

    /**
     * The kit with five members' games in November — three of them at 7, two reached at the same moment — and two
     * members' games in October, at 3 each.
     */
    private function played(): Kit
    {
        $kit = $this->kit();
        $kit->record('k1', 'member:1', 'game.played', '2026-11-03 10:00', 7);
        $kit->record('k2', 'member:4', 'game.played', '2026-11-02 10:00', 7);
        $kit->record('k3', 'member:2', 'game.played', '2026-11-02 10:00', 7);
        $kit->record('k4', 'member:5', 'game.played', '2026-11-01 10:00', 2);
        $kit->record('k5', 'member:3', 'game.played', '2026-11-05 10:00', 9);
        $kit->record('k6', 'member:1', 'game.played', '2026-10-02 10:00', 3);
        $kit->record('k7', 'member:2', 'game.played', '2026-10-02 10:00', 3);
        $kit->clock->set('2026-11-07 12:00 UTC');

        return $kit;
    }

    /**
     * @param list<Row> $rows
     *
     * @return list<string>
     */
    private function lines(array $rows): array
    {
        return array_map(static fn (Row $row): string => \sprintf('%d %d %s %s %s', $row->rank, $row->value, $row->member?->toString() ?? '-', $row->pseudonym ?? '-', $row->self ? 'self' : ''), $rows);
    }

    private function snapshotLine(Snapshot $snapshot): string
    {
        return \sprintf('snapshot %s %s %d [%s]', $snapshot->board, $snapshot->period, $snapshot->participants, implode(' ', array_map(static fn (Placing $placing): string => $placing->rank.':'.$placing->member->toString().'='.$placing->value, $snapshot->placings)));
    }

    /**
     * The value and the moment of the score of `member:1` on the board in the period.
     */
    private function held(Kit $kit, string $board, string $period): string
    {
        $score = $kit->scores->score($board, $period, $this->member('member:1'));

        return $score instanceof Score ? $score->value.' '.$score->reachedAt->format('Y-m-d H:i') : 'none';
    }

    /**
     * The snapshots due, taken now, as `board period`.
     *
     * @return list<string>
     */
    private function due(Kit $kit): array
    {
        return array_map(static fn (Snapshot $snapshot): string => $snapshot->board.' '.$snapshot->period, $kit->leaderboards->snapshotDue());
    }

    private function member(string $member): ResourceReference
    {
        return ResourceReference::fromString($member);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }
}
