<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\League;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Exception\PeriodNotOver;
use Tilttek\Leaderboards\Exception\UnknownPeriod;
use Tilttek\Leaderboards\LeaderboardProjection;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\League\Leagues;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\League\Movement;
use Tilttek\Leaderboards\League\Settlement;
use Tilttek\Leaderboards\Store\InMemoryLeagueStore;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\Leaderboards\Tests\Unit\Fixture\Clock;
use Tilttek\Leaderboards\Tests\Unit\Fixture\Kit;

/**
 * Weeks of a league in the Toronto timezone, where the week 2026-w45 runs from Monday 2 November 05:00 UTC to Monday
 * 9 November 05:00 UTC: three tiers, divisions of three, the first two of a division up and the last one down.
 */
#[CoversClass(Leagues::class)]
#[CoversClass(LeaderboardProjection::class)]
final class LeaguesTest extends TestCase
{
    public function testAMemberEntersADivisionWhenTheyFirstScoreInAWeek(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-03 13:00 UTC');

        foreach ([1 => 30, 2 => 20, 3 => 10, 4 => 40] as $member => $quantity) {
            $kit->record('a'.$member, 'member:'.$member, 'lesson.done', '2026-11-03 12:0'.$member, $quantity);
        }

        $kit->record('again', 'member:1', 'lesson.done', '2026-11-03 12:30', 5);
        $kit->record('elsewhere', 'member:5', 'game.played', '2026-11-03 12:31');

        self::assertSame(['bronze 1 member:1', 'bronze 1 member:2', 'bronze 1 member:3', 'bronze 2 member:4'], $this->lines($kit, '2026-w45'));
        self::assertEquals(new Membership('xp', '2026-w45', $this->member('member:1'), 'bronze', 1, new DateTimeImmutable('2026-11-03 12:01 UTC')), $kit->leagues->membership('xp', $this->member('member:1')));
        self::assertNull($kit->leagues->membership('xp', $this->member('member:5')));
        self::assertNull($kit->leagues->membership('xp', $this->member('member:1'), '2026-w44'));
        self::assertSame('bronze', $kit->leagues->tier('xp', $this->member('member:1')));
        self::assertSame(['member:1', 'member:2', 'member:3'], $kit->leagues->circle($kit->leagues->membership('xp', $this->member('member:2')) ?? self::fail())->members);
        self::assertCount(3, $kit->leagues->divisionOf($kit->leagues->membership('xp', $this->member('member:3')) ?? self::fail()));
        self::assertNull($kit->leagues->enter('plain', $this->member('member:1'), new DateTimeImmutable('2026-11-03 12:00 UTC')));
    }

    public function testThreeWeeksOfALeague(): void
    {
        $kit = $this->kit();

        // Week 45: six new members in two divisions of the lowest tier, the first two of each go up.
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 30, 2 => 20, 3 => 10, 4 => 40, 5 => 15, 6 => 5]);
        $kit->clock->set('2026-11-09 04:59:59 UTC');

        self::assertSame([], $this->settled($kit));

        $kit->clock->set('2026-11-09 05:00 UTC');

        self::assertSame(['xp 2026-w45 2026-11-09 05:00'], $this->settled($kit));
        self::assertSame(['settled xp 2026-w45 [member:1>promoted member:2>promoted member:3>stayed member:4>promoted member:5>promoted member:6>stayed]'], $kit->recorder->told);
        self::assertSame([], $this->settled($kit));
        self::assertSame(['silver', 'silver', 'bronze', 'silver', 'silver', 'bronze', 'bronze'], $this->tiers($kit, 7));

        // Week 46: the tiers reached; a full division of silver sends its last down, member 6 takes no part.
        $kit->clock->set('2026-11-10 13:00 UTC');
        $this->week($kit, '2026-11-10', [4 => 50, 1 => 5, 2 => 20, 5 => 1, 3 => 9, 7 => 3]);

        self::assertSame(['bronze 1 member:3', 'bronze 1 member:7', 'silver 1 member:4', 'silver 1 member:1', 'silver 1 member:2', 'silver 2 member:5'], $this->lines($kit, '2026-w46'));

        $kit->clock->set('2026-11-16 06:00 UTC');
        $kit->leagues->settleDue();

        self::assertSame('settled xp 2026-w46 [member:3>promoted member:7>promoted member:4>promoted member:2>promoted member:1>relegated member:5>promoted]', $kit->recorder->told[1]);
        self::assertSame(['bronze', 'gold', 'silver', 'gold', 'gold', 'bronze', 'silver'], $this->tiers($kit, 7));

        // Week 47: nobody goes above the highest tier, and its last goes down.
        $kit->clock->set('2026-11-17 13:00 UTC');
        $this->week($kit, '2026-11-17', [4 => 1, 2 => 2, 5 => 3, 6 => 1]);
        $kit->clock->set('2026-11-23 06:00 UTC');
        $kit->leagues->settleDue();

        self::assertSame('settled xp 2026-w47 [member:6>promoted member:5>stayed member:2>stayed member:4>relegated]', $kit->recorder->told[2]);
        self::assertSame(['bronze', 'gold', 'silver', 'silver', 'gold', 'silver', 'silver'], $this->tiers($kit, 7));
        self::assertSame(Movement::Relegated, $kit->leagues->membership('xp', $this->member('member:4'), '2026-w47')?->movement);
        self::assertSame(Movement::Promoted, $kit->leagues->membership('xp', $this->member('member:4'), '2026-w46')?->movement);
    }

    public function testAWeekStillOpenIsSettledBeforeAMemberEntersTheNext(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 30, 2 => 20, 3 => 10]);
        $kit->clock->set('2026-11-17 13:00 UTC');
        $this->week($kit, '2026-11-17', [1 => 1]);

        self::assertSame(['settled xp 2026-w45 [member:1>promoted member:2>promoted member:3>stayed]'], $kit->recorder->told);
        self::assertSame('silver', $kit->leagues->membership('xp', $this->member('member:1'), '2026-w47')?->tier);
    }

    public function testAnActivityOfAWeekSettledCountsWithoutADivision(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 30]);
        $kit->clock->set('2026-11-09 06:00 UTC');
        $kit->leagues->settleDue();
        $kit->record('late', 'member:2', 'lesson.done', '2026-11-08 12:00', 4);

        self::assertSame(4, $kit->scores->score('xp', '2026-w45', $this->member('member:2'))?->value);
        self::assertNull($kit->leagues->membership('xp', $this->member('member:2'), '2026-w45'));
        self::assertNull($kit->leagues->enter('xp', $this->member('member:2'), new DateTimeImmutable('2026-11-08 12:00 UTC')));
    }

    public function testAnActivityOfAWeekOverButOpenJoinsItWithoutSettlingIt(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 30]);
        $kit->clock->set('2026-11-09 06:00 UTC');
        $kit->record('late', 'member:2', 'lesson.done', '2026-11-08 12:00', 4);

        self::assertSame(['bronze 1 member:1', 'bronze 1 member:2'], $this->lines($kit, '2026-w45'));
        self::assertSame([], $kit->recorder->told);
    }

    public function testAMemberLeftWithoutAScoreRanksLast(): void
    {
        $kit = $this->kit();
        $kit->leagueStore->settle(new Settlement('xp', '2026-w44', new DateTimeImmutable('2026-11-02 06:00 UTC'), []), ['member:1' => 'silver', 'member:2' => 'silver', 'member:3' => 'silver']);
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 1, 2 => 20, 3 => 10]);
        $kit->journal->cancel('w2026-11-03-2');
        $kit->clock->set('2026-11-09 06:00 UTC');
        $kit->leagues->settleDue();

        self::assertSame(['settled xp 2026-w45 [member:3>promoted member:1>promoted member:2>relegated]'], $kit->recorder->told);
    }

    public function testALoneMemberLeftWithoutAScoreStillGoesUp(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 5]);
        $kit->journal->cancel('w2026-11-03-1');
        $kit->clock->set('2026-11-09 06:00 UTC');
        $kit->leagues->settleDue();

        self::assertSame(['settled xp 2026-w45 [member:1>promoted]'], $kit->recorder->told);
    }

    public function testSeveralWeeksDueAreSettledTogether(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 5]);
        $kit->leagueStore->join('xp', '2026-w46', $this->member('member:2'), 'bronze', new DateTimeImmutable('2026-11-10 12:00 UTC'), 3);
        $kit->clock->set('2026-11-17 06:00 UTC');

        self::assertSame(['xp 2026-w45 2026-11-17 06:00', 'xp 2026-w46 2026-11-17 06:00'], $this->settled($kit));
    }

    public function testAMemberPlacedInAWeekSettledKeepsTheirMembership(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 5]);
        $kit->clock->set('2026-11-09 06:00 UTC');
        $kit->leagues->settleDue();

        self::assertSame(Movement::Promoted, $kit->leagues->enter('xp', $this->member('member:1'), new DateTimeImmutable('2026-11-08 12:00 UTC'))?->movement);
    }

    public function testATierTheLeagueNoLongerHasCountsAsTheLowest(): void
    {
        $kit = $this->kit();
        $kit->leagueStore->settle(new Settlement('xp', '2026-w44', new DateTimeImmutable('2026-11-02 06:00 UTC'), []), ['member:1' => 'ruby']);
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 1]);

        self::assertSame('bronze', $kit->leagues->tier('xp', $this->member('member:1')));
        self::assertSame(['bronze 1 member:1'], $this->lines($kit, '2026-w45'));
    }

    public function testAWeekIsSettledOnceAndOnlyWhenOver(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 30]);

        try {
            $kit->leagues->settle('xp', '2026-w45');
            self::fail('A week under way is not settled.');
        } catch (PeriodNotOver $periodNotOver) {
            self::assertSame('The period "2026-w45" of the board "xp" is not over: it cannot be frozen yet.', $periodNotOver->getMessage());
        }

        $kit->clock->set('2026-11-09 05:00 UTC');

        self::assertSame(['member:1'], array_map(static fn (Membership $membership): string => $membership->member->toString(), $kit->leagues->settle('xp', '2026-w45')->memberships ?? []));
        self::assertNull($kit->leagues->settle('xp', '2026-w45'));
        self::assertCount(1, $kit->recorder->told);
        self::assertSame('2026-w44', $kit->leagues->settle('xp', '2026-w44')?->week);
    }

    public function testABoardWithoutALeague(): void
    {
        $kit = $this->kit();

        try {
            $kit->leagues->membership('plain', $this->member('member:1'));
            self::fail('A board without a league has no membership.');
        } catch (InvalidLeaderboard $invalidLeaderboard) {
            self::assertSame('The board "plain" has no league.', $invalidLeaderboard->getMessage());
        }

        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The board "plain" has no league.');

        $kit->leagues->tier('plain', $this->member('member:1'));
    }

    public function testABoardWithoutALeagueIsNotSettled(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The board "plain" has no league.');

        $this->kit()->leagues->settle('plain', '2026-w44');
    }

    public function testAnUnknownWeek(): void
    {
        $this->expectException(UnknownPeriod::class);
        $this->expectExceptionMessageIsOrContains('"2026-w99"');

        $this->kit()->leagues->membership('xp', $this->member('member:1'), '2026-w99');
    }

    public function testTheLeagueOfABoard(): void
    {
        $kit = $this->kit();

        self::assertEquals(new League(['bronze', 'silver', 'gold'], 3, 2, 1), $kit->leagues->league('xp'));

        $this->expectException(InvalidLeaderboard::class);

        $kit->leagues->league('plain');
    }

    public function testALeagueNeedsNoObserver(): void
    {
        $boards = $this->kit()->boards;
        $scores = new InMemoryScoreStore();
        $clock = new Clock('2026-11-03 13:00 UTC');
        $leagues = new Leagues($boards, $scores, new InMemoryLeagueStore(), $clock);
        $leagues->enter('xp', $this->member('member:1'), new DateTimeImmutable('2026-11-03 12:00 UTC'));

        $clock->set('2026-11-09 06:00 UTC');

        self::assertCount(1, $leagues->settleDue());
    }

    public function testAFutureMomentSettlesOnlyTheWeeksOverNow(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 30]);
        $kit->leagues->enter('xp', $this->member('member:2'), new DateTimeImmutable('2026-11-10 12:00 UTC'));

        self::assertSame([], $kit->recorder->told);
        self::assertSame('bronze', $kit->leagues->membership('xp', $this->member('member:2'), '2026-w46')?->tier);
    }

    public function testExportAndErasure(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 30, 2 => 20]);
        $kit->clock->set('2026-11-09 06:00 UTC');
        $kit->leagues->settleDue();

        self::assertSame([
            'tiers' => ['xp' => 'silver'],
            'memberships' => [['board' => 'xp', 'week' => '2026-w45', 'tier' => 'bronze', 'division' => 1, 'cohort' => 0, 'joined_at' => '2026-11-03T12:01:00+00:00', 'movement' => 'promoted']],
        ], $kit->leagues->export($this->member('member:1')));
        self::assertSame(2, $kit->leagues->erase($this->member('member:1')));
        self::assertSame(['tiers' => [], 'memberships' => []], $kit->leagues->export($this->member('member:1')));
    }

    public function testDivisionsFormedByTheVolumeOfTheWeekBefore(): void
    {
        $kit = new Kit(Boards::fromArray([
            'xp' => ['measure' => 'total', 'verbs' => ['lesson.done'], 'period' => 'week', 'league' => ['tiers' => ['bronze'], 'division_size' => 3, 'promoted' => 0, 'relegated' => 0, 'formed_by' => 'volume']],
        ], 'America/Toronto'), '2026-11-03 13:00 UTC');
        $this->week($kit, '2026-11-03', [1 => 1, 2 => 2, 3 => 3, 4 => 100, 5 => 120]);

        self::assertSame([0, 0, 0, 0, 0], array_map(fn (int $member): int => $kit->leagues->membership('xp', $this->member('member:'.$member))->cohort ?? -1, range(1, 5)));

        $kit->clock->set('2026-11-10 13:00 UTC');
        $this->week($kit, '2026-11-10', [6 => 1, 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 7 => 1]);

        self::assertSame(['bronze 1 member:6', 'bronze 1 member:7', 'bronze 2 member:1', 'bronze 3 member:2', 'bronze 3 member:3', 'bronze 4 member:4', 'bronze 4 member:5'], $this->lines($kit, '2026-w46'));
        self::assertSame([0, 1, 2, 2, 7, 7, 0], array_map(fn (int $member): int => $kit->leagues->membership('xp', $this->member('member:'.$member))->cohort ?? -1, [6, 1, 2, 3, 4, 5, 7]));
    }

    private function kit(): Kit
    {
        return new Kit(Boards::fromArray([
            'xp' => ['measure' => 'total', 'verbs' => ['lesson.done'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver', 'gold'], 'division_size' => 3, 'promoted' => 2, 'relegated' => 1]],
            'plain' => ['measure' => 'total', 'verbs' => ['lesson.done'], 'period' => 'week'],
        ], 'America/Toronto'), '2026-11-03 13:00 UTC');
    }

    /**
     * Records one lesson of each member, a minute apart from noon UTC that day, in the order given.
     *
     * @param array<int, int> $quantities by member number
     */
    private function week(Kit $kit, string $day, array $quantities): void
    {
        $minute = 0;

        foreach ($quantities as $member => $quantity) {
            $kit->record('w'.$day.'-'.$member, 'member:'.$member, 'lesson.done', \sprintf('%s 12:%02d', $day, ++$minute), $quantity);
        }
    }

    /**
     * @return list<string>
     */
    private function settled(Kit $kit): array
    {
        return array_map(static fn (Settlement $settlement): string => $settlement->board.' '.$settlement->week.' '.$settlement->settledAt->format('Y-m-d H:i'), $kit->leagues->settleDue());
    }

    /**
     * @return list<string>
     */
    private function lines(Kit $kit, string $week): array
    {
        return array_map(static fn (Membership $membership): string => $membership->tier.' '.$membership->division.' '.$membership->member->toString(), $kit->leagueStore->memberships('xp', $week));
    }

    /**
     * @return list<string>
     */
    private function tiers(Kit $kit, int $members): array
    {
        return array_map(fn (int $member): string => $kit->leagues->tier('xp', $this->member('member:'.$member)), range(1, $members));
    }

    private function member(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
