<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\League;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Leaderboards;
use Tilttek\Leaderboards\League\Movement;
use Tilttek\Leaderboards\Row;
use Tilttek\Leaderboards\Store\InMemoryAppearanceStore;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\Leaderboards\Store\InMemorySnapshotStore;
use Tilttek\Leaderboards\Tests\Unit\Fixture\Clock;
use Tilttek\Leaderboards\Tests\Unit\Fixture\Kit;

/**
 * A member's division as `Leaderboards` shows it: only its members, each by their choice of appearance.
 */
#[CoversClass(Leaderboards::class)]
final class DivisionStandingTest extends TestCase
{
    public function testAMemberSeesOnlyTheirDivision(): void
    {
        $kit = $this->kit();
        $kit->leaderboards->appear($this->member('member:4'), Appearance::named());
        $kit->leaderboards->appear($this->member('member:3'), Appearance::pseudonym('Gris'));

        $division = $kit->leaderboards->division('xp', $this->member('member:2'));

        self::assertNotNull($division);
        self::assertSame(['bronze', 1, '2026-w45'], [$division->membership->tier, $division->membership->division, $division->membership->week]);
        self::assertSame([3, 2, 0], [$division->members, $division->promoted, $division->relegated]);
        self::assertSame(3, $division->standing->participants);
        self::assertSame('2026-w45', $division->standing->period->code);
        self::assertSame(
            ['1 30 hidden', '2 20 member:2 self', '3 10 Gris'],
            array_map(static fn (Row $row): string => $row->rank.' '.$row->value.' '.($row->shownAs() ?? 'hidden').($row->self ? ' self' : ''), $division->standing->rows),
        );
        self::assertSame(2, $division->standing->own?->rank);

        $other = $kit->leaderboards->division('xp', $this->member('member:4'));

        self::assertSame(['1 40 member:4 self'], array_map(static fn (Row $row): string => $row->rank.' '.$row->value.' '.$row->shownAs().($row->self ? ' self' : ''), $other->standing->rows ?? []));
        self::assertNull($kit->leaderboards->division('xp', $this->member('member:9')));
        self::assertNull($kit->leaderboards->division('xp', $this->member('member:2'), '2026-w44'));
    }

    public function testADivisionOfAHigherTierShowsWhoGoesDown(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-10 13:00 UTC');

        foreach ([1 => 5, 2 => 20, 4 => 50] as $member => $quantity) {
            $kit->record('next-'.$member, 'member:'.$member, 'lesson.done', '2026-11-10 12:0'.$member, $quantity);
        }

        $division = $kit->leaderboards->division('xp', $this->member('member:1'));

        self::assertSame(['silver', 3, 2, 1], [$division?->membership->tier, $division?->members, $division?->promoted, $division?->relegated]);
        self::assertSame(Movement::Relegated, $division?->movementAt($division->standing->own->rank ?? 0));
        self::assertSame(['bronze', 1], [$kit->leaderboards->division('xp', $this->member('member:2'), '2026-w45')?->membership->tier, $kit->leaderboards->division('xp', $this->member('member:2'), '2026-w45')?->membership->division]);
    }

    public function testADivisionShowsAHundredLinesAtMost(): void
    {
        $kit = new Kit(Boards::fromArray([
            'xp' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['bronze'], 'division_size' => 100, 'promoted' => 0, 'relegated' => 0]],
        ]), '2026-11-03 13:00 UTC');

        foreach (range(1, 101) as $member) {
            $kit->leagueStore->join('xp', '2026-w45', $this->member('member:'.$member), 'bronze', new DateTimeImmutable('2026-11-03 12:00 UTC'), 101);
            $kit->leaderboards->report('xp', $this->member('member:'.$member), $member, new DateTimeImmutable('2026-11-03 12:00 UTC'));
        }

        $division = $kit->leaderboards->division('xp', $this->member('member:1'));

        self::assertNotNull($division);
        self::assertSame(101, $division->members);
        self::assertCount(100, $division->standing->rows);
        self::assertSame(101, $division->standing->own?->rank);
    }

    public function testAReportedValueEntersADivision(): void
    {
        $kit = new Kit(Boards::fromArray([
            'level' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['low', 'high']]],
        ]), '2026-11-03 13:00 UTC');

        self::assertTrue($kit->leaderboards->report('level', $this->member('member:1'), 4));
        self::assertSame('low', $kit->leagues->membership('level', $this->member('member:1'))?->tier);
    }

    public function testADivisionNeedsTheLeagues(): void
    {
        $boards = $this->kit()->boards;
        $leaderboards = new Leaderboards($boards, new InMemoryScoreStore(), new InMemorySnapshotStore(), new InMemoryAppearanceStore(), new Clock());

        self::assertTrue($leaderboards->report('level', $this->member('member:1'), 4));
        self::assertSame(['tiers' => [], 'memberships' => []], $leaderboards->export($this->member('member:1'))['leagues']);
        self::assertSame(0, $leaderboards->erase($this->member('member:1'))->leagues);

        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The leaderboards are not given their leagues.');

        $leaderboards->division('xp', $this->member('member:1'));
    }

    public function testExportAndErasureHoldTheLeagues(): void
    {
        $kit = $this->kit();
        $kit->clock->set('2026-11-09 06:00 UTC');
        $kit->leagues->settleDue();

        self::assertSame(['xp' => 'silver'], $kit->leaderboards->export($this->member('member:1'))['leagues']['tiers']);
        self::assertSame(2, $kit->leaderboards->erase($this->member('member:1'))->leagues);
    }

    /**
     * Four members of the lowest tier in week 45: three in the first division, at 30, 20 and 10, one in the second.
     */
    private function kit(): Kit
    {
        $kit = new Kit(Boards::fromArray([
            'xp' => ['measure' => 'total', 'verbs' => ['lesson.done'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver', 'gold'], 'division_size' => 3, 'promoted' => 2, 'relegated' => 1]],
            'level' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['low', 'high']]],
        ], 'America/Toronto'), '2026-11-03 13:00 UTC');

        foreach ([1 => 30, 2 => 20, 3 => 10, 4 => 40] as $member => $quantity) {
            $kit->record('a'.$member, 'member:'.$member, 'lesson.done', '2026-11-03 12:0'.$member, $quantity);
        }

        return $kit;
    }

    private function member(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }
}
