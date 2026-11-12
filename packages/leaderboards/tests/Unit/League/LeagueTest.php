<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\League;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\League\Division;
use Tilttek\Leaderboards\League\Formation;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\League\Movement;
use Tilttek\Leaderboards\League\Settlement;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Period\PeriodKind;
use Tilttek\Leaderboards\Period\Schedule;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Standing;

#[CoversClass(League::class)]
#[CoversClass(Board::class)]
#[CoversClass(Boards::class)]
#[CoversClass(Membership::class)]
#[CoversClass(Settlement::class)]
#[CoversClass(Division::class)]
#[CoversClass(Movement::class)]
final class LeagueTest extends TestCase
{
    public function testALeague(): void
    {
        $league = new League(['bronze', 'silver', 'gold']);

        self::assertSame(['bronze', 'silver', 'gold'], $league->tiers);
        self::assertSame([30, 7, 5], [$league->divisionSize, $league->promoted, $league->relegated]);
        self::assertSame('bronze', $league->lowest());
        self::assertSame([0, 1, 2, 0], [$league->level('bronze'), $league->level('silver'), $league->level('gold'), $league->level('ruby')]);
    }

    public function testWhereAMovementLeads(): void
    {
        $league = new League(['bronze', 'silver', 'gold']);

        self::assertSame(['silver', 'bronze', 'bronze'], [$league->after('bronze', Movement::Promoted), $league->after('bronze', Movement::Stayed), $league->after('bronze', Movement::Relegated)]);
        self::assertSame(['gold', 'silver', 'bronze'], [$league->after('silver', Movement::Promoted), $league->after('silver', Movement::Stayed), $league->after('silver', Movement::Relegated)]);
        self::assertSame(['gold', 'gold', 'silver'], [$league->after('gold', Movement::Promoted), $league->after('gold', Movement::Stayed), $league->after('gold', Movement::Relegated)]);
        self::assertSame('silver', $league->after('ruby', Movement::Promoted));
    }

    public function testHowManyGoUpAndDown(): void
    {
        $league = new League(['bronze', 'silver', 'gold'], 30, 7, 5);

        self::assertSame([7, 0], [$league->promotedFrom('bronze', 30), $league->relegatedFrom('bronze', 30)]);
        self::assertSame([7, 5], [$league->promotedFrom('silver', 30), $league->relegatedFrom('silver', 30)]);
        self::assertSame([0, 5], [$league->promotedFrom('gold', 30), $league->relegatedFrom('gold', 30)]);
        self::assertSame([7, 5], [$league->promotedFrom('silver', 31), $league->relegatedFrom('silver', 31)]);
        self::assertSame([7, 1], [$league->promotedFrom('silver', 26), $league->relegatedFrom('silver', 26)]);
        self::assertSame([7, 0], [$league->promotedFrom('silver', 25), $league->relegatedFrom('silver', 25)]);
        self::assertSame([3, 0], [$league->promotedFrom('silver', 3), $league->relegatedFrom('silver', 3)]);
        self::assertSame([0, 0], [$league->promotedFrom('gold', 1), $league->relegatedFrom('gold', 1)]);
        self::assertSame([1, 0], [$league->promotedFrom('ruby', 1), $league->relegatedFrom('ruby', 30)]);
        self::assertSame([0, 0], [new League(['only'])->promotedFrom('only', 30), new League(['only'])->relegatedFrom('only', 30)]);
    }

    public function testTheLimitsOfALeague(): void
    {
        $tiers = array_map(static fn (int $tier): string => 't'.$tier, range(1, 50));

        self::assertCount(50, new League($tiers)->tiers);
        self::assertSame(2, new League(['a'], 2, 1, 1)->divisionSize);
        self::assertSame(100, new League(['a'], 100, 0, 0)->divisionSize);
        self::assertSame([0, 0], [new League(['a'], 2, 0, 0)->promoted, new League(['a'], 2, 0, 0)->relegated]);
        self::assertSame([2, 0], [new League(['a'], 2, 2, 0)->promoted, new League(['a'], 2, 2, 0)->relegated]);
        self::assertSame([0, 2], [new League(['a'], 2, 0, 2)->promoted, new League(['a'], 2, 0, 2)->relegated]);
    }

    /**
     * @return iterable<string, array{list<string>, int, int, int, string}>
     */
    public static function refusedLeagues(): iterable
    {
        yield 'no tier' => [[], 30, 7, 5, 'A league has 1 to 50 tiers, each named once.'];
        yield 'too many tiers' => [array_map(static fn (int $tier): string => 't'.$tier, range(1, 51)), 30, 7, 5, 'A league has 1 to 50 tiers, each named once.'];
        yield 'a tier twice' => [['a', 'b', 'a'], 30, 7, 5, 'A league has 1 to 50 tiers, each named once.'];
        yield 'a tier badly named' => [['Gold'], 30, 7, 5, '"Gold" is not a tier name'];
        yield 'a division of one' => [['a'], 1, 0, 0, 'A division holds 2 to 100 members; 1 is not a division size.'];
        yield 'a division too large' => [['a'], 101, 7, 5, 'A division holds 2 to 100 members; 101 is not a division size.'];
        yield 'fewer than none up' => [['a'], 30, -1, 5, 'A division of 30 sends 0 or more members up and 0 or more down, 30 at most in all; -1 up and 5 down do not fit.'];
        yield 'fewer than none down' => [['a'], 30, 7, -1, '7 up and -1 down do not fit.'];
        yield 'more moved than members' => [['a'], 10, 6, 5, 'A division of 10 sends 0 or more members up and 0 or more down, 10 at most in all; 6 up and 5 down do not fit.'];
    }

    /**
     * @param list<string> $tiers
     */
    #[DataProvider('refusedLeagues')]
    public function testALeagueIsRefused(array $tiers, int $size, int $promoted, int $relegated, string $message): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains($message);

        new League($tiers, $size, $promoted, $relegated);
    }

    public function testALeagueIsWeekly(): void
    {
        $league = new League(['bronze']);

        self::assertSame($league, new Board('xp', Measure::Total, new Schedule(PeriodKind::Week), ['lesson.done'], league: $league)->league);
        self::assertNull(new Board('xp', Measure::Total, new Schedule(PeriodKind::Week), ['lesson.done'])->league);

        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The board "xp" has a league: it ranks by week, and not by object.');

        new Board('xp', Measure::Total, new Schedule(PeriodKind::Month), ['lesson.done'], league: $league);
    }

    public function testALeagueFromArray(): void
    {
        $boards = Boards::fromArray([
            'xp' => ['measure' => 'total', 'verbs' => ['lesson.done'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver']]],
            'tuned' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['low', 'high'], 'division_size' => 20, 'promoted' => 3, 'relegated' => 2]],
            'plain' => ['measure' => 'latest', 'period' => 'week', 'league' => null],
        ]);

        self::assertEquals(new League(['bronze', 'silver'], 30, 7, 5), $boards->get('xp')->league);
        self::assertEquals(new League(['low', 'high'], 20, 3, 2), $boards->get('tuned')->league);
        self::assertNull($boards->get('plain')->league);
    }

    public function testAMembership(): void
    {
        $membership = new Membership('xp', '2026-w45', ResourceReference::fromString('member:1'), 'gold', 1, new DateTimeImmutable('2026-11-03 12:00', new DateTimeZone('America/Toronto')));

        self::assertNull($membership->movement);
        self::assertSame(['board' => 'xp', 'week' => '2026-w45', 'tier' => 'gold', 'division' => 1, 'cohort' => 0, 'joined_at' => '2026-11-03T12:00:00-05:00', 'movement' => null], $membership->toArray());

        $settled = $membership->settled(Movement::Relegated);

        self::assertSame(Movement::Relegated, $settled->movement);
        self::assertSame('relegated', $settled->toArray()['movement']);
        self::assertEquals($membership, new Membership($settled->board, $settled->week, $settled->member, $settled->tier, $settled->division, $settled->joinedAt));
        self::assertTrue($membership->sharesDivisionWith($settled));
        self::assertTrue($membership->sharesDivisionWith(new Membership('xp', '2026-w45', ResourceReference::fromString('member:2'), 'gold', 1, $membership->joinedAt)));
        self::assertFalse($membership->sharesDivisionWith(new Membership('xp', '2026-w45', $membership->member, 'gold', 2, $membership->joinedAt)));
        self::assertFalse($membership->sharesDivisionWith(new Membership('xp', '2026-w45', $membership->member, 'silver', 1, $membership->joinedAt)));
        self::assertFalse($membership->sharesDivisionWith(new Membership('xp', '2026-w46', $membership->member, 'gold', 1, $membership->joinedAt)));
        self::assertFalse($membership->sharesDivisionWith(new Membership('other', '2026-w45', $membership->member, 'gold', 1, $membership->joinedAt)));
    }

    public function testADivisionIsNumberedFromOne(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('Divisions are numbered from 1; 0 is not one.');

        new Membership('xp', '2026-w45', ResourceReference::fromString('member:1'), 'gold', 0, new DateTimeImmutable());
    }

    public function testASettlement(): void
    {
        $at = new DateTimeImmutable('2026-11-09 06:00 UTC');
        $up = new Membership('xp', '2026-w45', ResourceReference::fromString('member:1'), 'gold', 1, $at, Movement::Promoted);
        $stay = new Membership('xp', '2026-w45', ResourceReference::fromString('member:2'), 'gold', 1, $at, Movement::Stayed);
        $down = new Membership('xp', '2026-w45', ResourceReference::fromString('member:3'), 'gold', 1, $at, Movement::Relegated);
        $settlement = new Settlement('xp', '2026-w45', $at, [$up, $stay, $down]);

        self::assertSame([$up], $settlement->moved(Movement::Promoted));
        self::assertSame([$stay], $settlement->moved(Movement::Stayed));
        self::assertSame([$down], $settlement->moved(Movement::Relegated));
        self::assertSame([], new Settlement('xp', '2026-w45', $at, [$down])->moved(Movement::Promoted));
        self::assertSame([$down], new Settlement('xp', '2026-w45', $at, [$up, $down])->moved(Movement::Relegated));
    }

    public function testWhereALineOfADivisionGoes(): void
    {
        $board = new Board('xp', Measure::Total, new Schedule(PeriodKind::Week), ['lesson.done'], league: new League(['a', 'b']));
        $membership = new Membership('xp', '2026-w45', ResourceReference::fromString('member:1'), 'a', 1, new DateTimeImmutable());
        $division = new Division($membership, new Standing($board, new Period('2026-w45'), []), 10, 2, 3);

        self::assertSame([10, 2, 3], [$division->members, $division->promoted, $division->relegated]);
        self::assertSame(
            [Movement::Promoted, Movement::Promoted, Movement::Stayed, Movement::Stayed, Movement::Stayed, Movement::Stayed, Movement::Stayed, Movement::Relegated, Movement::Relegated, Movement::Relegated],
            array_map($division->movementAt(...), range(1, 10)),
        );
    }

    public function testTheCohortsByVolume(): void
    {
        $league = new League(['bronze'], formedBy: Formation::Volume);

        self::assertSame([0, 0, 1, 2, 2, 3, 3, 7, 7, 8, 50], array_map($league->cohortOf(...), [-1, 0, 1, 2, 3, 4, 7, 64, 127, 128, Score::MAX]));
        self::assertSame(Formation::Arrival, new League(['bronze'])->formedBy);
        self::assertSame(Formation::Volume, Boards::fromArray(['xp' => ['measure' => 'total', 'verbs' => ['lesson.done'], 'period' => 'week', 'league' => ['tiers' => ['bronze'], 'formed_by' => 'volume']]])->get('xp')->league?->formedBy);
    }

    public function testAnUnknownFormationIsRefused(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The divisions of the league of the board "xp" are formed by "arrival" or by "volume"; "chance" is neither.');

        Boards::fromArray(['xp' => ['measure' => 'total', 'verbs' => ['lesson.done'], 'period' => 'week', 'league' => ['tiers' => ['bronze'], 'formed_by' => 'chance']]]);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function refusedCohorts(): iterable
    {
        yield 'below zero' => [-1];
        yield 'above the most' => [65];
    }

    #[DataProvider('refusedCohorts')]
    public function testACohortIsRefused(int $cohort): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A cohort is 0 to 64; %d is not one.', $cohort));

        new Membership('xp', '2026-w45', ResourceReference::fromString('member:1'), 'bronze', 1, new DateTimeImmutable('2026-11-03 UTC'), cohort: $cohort);
    }

    public function testASettledMembershipKeepsItsCohort(): void
    {
        self::assertSame(4, new Membership('xp', '2026-w45', ResourceReference::fromString('member:1'), 'bronze', 1, new DateTimeImmutable('2026-11-03 UTC'), cohort: 4)->settled(Movement::Stayed)->cohort);
    }
}
