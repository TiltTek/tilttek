<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\League\Movement;
use Tilttek\Leaderboards\League\Settlement;
use Tilttek\Leaderboards\Placing;
use Tilttek\Leaderboards\Snapshot;
use Tilttek\Leaderboards\Store\InMemoryAppearanceStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\CelebrateOn;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\LeagueSettled;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\SnapshotTaken;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\Summit;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\TopReached;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Lead;
use Tilttek\LeaderboardsBundle\Infrastructure\Twig\CelebrationView;
use Tilttek\LeaderboardsBundle\Infrastructure\Twig\LeaderboardsExtension;
use Tilttek\LeaderboardsBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(Summit::class)]
#[CoversClass(CelebrateOn::class)]
#[CoversClass(TopReached::class)]
#[CoversClass(DispatchingObserver::class)]
#[CoversClass(CelebrationView::class)]
#[CoversClass(LeaderboardsExtension::class)]
final class CelebrationTest extends TestCase
{
    public function testAFirstPlaceIsTheTop(): void
    {
        self::assertSame(Summit::FirstPlace, Summit::ofPlacing($this->placing(1)));
        self::assertNull(Summit::ofPlacing($this->placing(2)));
        self::assertNull(Summit::ofPlacing($this->placing(0)));
    }

    public function testAPromotionIntoTheHighestTierIsTheTop(): void
    {
        $tiers = ['bronze', 'silver', 'gold'];

        self::assertSame(Summit::HighestTier, Summit::ofMembership($this->membership('silver', Movement::Promoted), $tiers));
        self::assertNull(Summit::ofMembership($this->membership('bronze', Movement::Promoted), $tiers));
        self::assertNull(Summit::ofMembership($this->membership('silver', Movement::Stayed), $tiers));
        self::assertNull(Summit::ofMembership($this->membership('gold', Movement::Stayed), $tiers));
        self::assertNull(Summit::ofMembership($this->membership('silver', null), $tiers));
        self::assertSame(Summit::HighestTier, Summit::ofMembership($this->membership('bronze', Movement::Promoted), ['bronze', 'silver']));
        self::assertNull(Summit::ofMembership($this->membership('bronze', Movement::Promoted), ['bronze']));
        self::assertSame(['first_place', 'lead', 'highest_tier'], [Summit::FirstPlace->value, Summit::Lead->value, Summit::HighestTier->value]);
        self::assertSame(['period_close', 'score_change'], [CelebrateOn::PeriodClose->value, CelebrateOn::ScoreChange->value]);
    }

    public function testASnapshotTellsWhoFinishedFirst(): void
    {
        $dispatcher = $this->dispatcher();
        $at = new DateTimeImmutable('2026-11-01 UTC');
        $snapshot = new Snapshot('games', '2026-10', $at, 3, [$this->placing(1, 'member:1'), $this->placing(1, 'member:2'), $this->placing(3, 'member:3')]);

        new DispatchingObserver($dispatcher)->snapshotTaken($snapshot);

        self::assertEquals([
            new SnapshotTaken($snapshot),
            new TopReached('games', '2026-10', ResourceReference::fromString('member:1'), Summit::FirstPlace, $at),
            new TopReached('games', '2026-10', ResourceReference::fromString('member:2'), Summit::FirstPlace, $at),
        ], $dispatcher->events);
    }

    public function testAFirstPlaceIsNotToldAtTheCloseWhenTheLeadIs(): void
    {
        $dispatcher = $this->dispatcher();
        $snapshot = new Snapshot('games', '2026-10', new DateTimeImmutable('2026-11-01 UTC'), 1, [$this->placing(1, 'member:1')]);

        new DispatchingObserver($dispatcher, celebrateOn: CelebrateOn::ScoreChange)->snapshotTaken($snapshot);

        self::assertEquals([new SnapshotTaken($snapshot)], $dispatcher->events);
    }

    public function testTheEventsCarryHowTheMembersChoseToAppear(): void
    {
        $dispatcher = $this->dispatcher();
        $appearances = new InMemoryAppearanceStore();
        $appearances->choose(ResourceReference::fromString('member:1'), Appearance::pseudonym('Gris'));
        $appearances->choose(ResourceReference::fromString('member:3'), Appearance::named());

        $at = new DateTimeImmutable('2026-11-01 UTC');
        $boards = Boards::fromArray(['weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver']]]]);
        $observer = new DispatchingObserver($dispatcher, $boards, appearances: $appearances);

        $observer->snapshotTaken(new Snapshot('games', '2026-10', $at, 2, [$this->placing(1, 'member:1'), $this->placing(1, 'member:2')]));
        $observer->leagueSettled(new Settlement('weekly', '2026-w45', $at, [$this->membership('bronze', Movement::Promoted, 'member:3')]));

        $tops = array_values(array_filter($dispatcher->events, static fn (object $event): bool => $event instanceof TopReached));
        self::assertEquals([Appearance::pseudonym('Gris'), Appearance::hidden(), Appearance::named()], array_map(static fn (TopReached $event): Appearance => $event->appearance, $tops));
    }

    public function testASettlementTellsWhoReachedTheHighestTier(): void
    {
        $dispatcher = $this->dispatcher();
        $at = new DateTimeImmutable('2026-11-09 UTC');
        $settlement = new Settlement('weekly', '2026-w45', $at, [
            $this->membership('silver', Movement::Promoted, 'member:1'),
            $this->membership('bronze', Movement::Promoted, 'member:2'),
            $this->membership('gold', Movement::Stayed, 'member:3'),
        ]);
        $boards = Boards::fromArray(['weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver', 'gold']]], 'games' => ['measure' => 'total', 'verbs' => ['game.played']]]);

        new DispatchingObserver($dispatcher, $boards)->leagueSettled($settlement);

        self::assertEquals([
            new LeagueSettled($settlement),
            new TopReached('weekly', '2026-w45', ResourceReference::fromString('member:1'), Summit::HighestTier, $at),
        ], $dispatcher->events);

        $quiet = $this->dispatcher();
        new DispatchingObserver($quiet)->leagueSettled($settlement);
        new DispatchingObserver($quiet, $boards)->leagueSettled(new Settlement('games', '2026-w45', $at, [$this->membership('silver', Movement::Promoted)]));
        new DispatchingObserver($quiet, $boards)->leagueSettled(new Settlement('gone', '2026-w45', $at, [$this->membership('silver', Movement::Promoted)]));

        self::assertSame([LeagueSettled::class, LeagueSettled::class, LeagueSettled::class], array_map(static fn (object $event): string => $event::class, $quiet->events));
    }

    public function testTheMemberFirstWhenThePeriodClosedIsCelebrated(): void
    {
        $kit = new Kit(Boards::fromArray([
            'games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month'],
            'ever' => ['measure' => 'total', 'verbs' => ['game.played']],
        ]), '2026-10-10 12:00 UTC');
        $kit->record('k1', 'member:1', 'game.played', '2026-10-10 10:00', 5);
        $kit->record('k2', 'member:2', 'game.played', '2026-10-10 11:00', 3);
        $kit->clock->set('2026-11-02 12:00 UTC');
        $kit->leaderboards->snapshot('games', '2026-10');

        $extension = new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, clock: $kit->clock, celebration: 'fireworks');

        $celebration = $extension->celebration('games', 'member:1');

        self::assertInstanceOf(CelebrationView::class, $celebration);
        self::assertSame(['games', '2026-10', Summit::FirstPlace, 'fireworks', null], [$celebration->board->name, $celebration->period, $celebration->summit, $celebration->effect, $celebration->tier]);
        self::assertNull($extension->celebration('games', 'member:2'));
        self::assertNull($extension->celebration('games', 'member:9'));
        self::assertNull($extension->celebration('games', 'member:1', '2026-09'));
        self::assertSame('2026-10', $extension->celebration('games', 'member:1', '2026-10')?->period);
        self::assertNull($extension->celebration('ever', 'member:1'));
        self::assertNull(new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, clock: $kit->clock)->celebration('games', 'member:1'));
    }

    public function testThePeriodClosedIsReadAtTheMomentOfTheClock(): void
    {
        $kit = new Kit(Boards::fromArray([
            'games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month'],
        ]), '2020-10-10 12:00 UTC');
        $kit->record('k1', 'member:1', 'game.played', '2020-10-10 10:00', 5);
        $kit->clock->set('2020-11-02 12:00 UTC');
        $kit->leaderboards->snapshot('games', '2020-10');

        self::assertSame('2020-10', new LeaderboardsExtension($kit->leaderboards, 'ranks', clock: $kit->clock, celebration: 'confetti')->celebration('games', 'member:1')?->period);
        // Without a clock the period closed is the one before now, which holds no snapshot here.
        self::assertNull(new LeaderboardsExtension($kit->leaderboards, 'ranks', celebration: 'confetti')->celebration('games', 'member:1'));
    }

    public function testTheMemberPromotedIntoTheHighestTierIsCelebrated(): void
    {
        $kit = new Kit(Boards::fromArray([
            'weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver'], 'promoted' => 1, 'relegated' => 0]],
        ]), '2026-11-03 12:00 UTC');
        $kit->record('k1', 'member:1', 'game.played', '2026-11-03 10:00', 5);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-03 11:00', 3);
        $kit->clock->set('2026-11-10 12:00 UTC');
        $kit->leagues->settleDue();

        $extension = new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, clock: $kit->clock, celebration: 'confetti');

        $celebration = $extension->celebration('weekly', 'member:1');

        self::assertInstanceOf(CelebrationView::class, $celebration);
        self::assertSame(['2026-w45', Summit::HighestTier, 'confetti', 'silver'], [$celebration->period, $celebration->summit, $celebration->effect, $celebration->tier]);
        self::assertNull($extension->celebration('weekly', 'member:2'));
        self::assertNull(new LeaderboardsExtension($kit->leaderboards, 'ranks', clock: $kit->clock, celebration: 'confetti')->celebration('weekly', 'member:1'));
    }

    public function testTheMemberWhoLeadsThePeriodUnderWayIsCelebratedAsScoresChange(): void
    {
        $kit = new Kit(Boards::fromArray([
            'games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month'],
        ]), '2026-11-10 12:00 UTC', $this->dispatcher());
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', 5);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-03 10:00', 6);

        $extension = new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, clock: $kit->clock, celebration: 'confetti', celebrateOn: CelebrateOn::ScoreChange, leads: $kit->leads);
        $celebration = $extension->celebration('games', 'member:2');

        self::assertInstanceOf(CelebrationView::class, $celebration);
        self::assertSame(['games', '2026-11', Summit::Lead, 'confetti', null], [$celebration->board->name, $celebration->period, $celebration->summit, $celebration->effect, $celebration->tier]);
        self::assertNull($extension->celebration('games', 'member:1'), 'Alone on the board, the first score took no lead.');
        self::assertNull($extension->celebration('games', 'member:9'));
        self::assertNull($extension->celebration('games', 'member:2', '2026-10'));
        self::assertSame('2026-11', $extension->celebration('games', 'member:2', '2026-11')?->period);

        $kit->record('k3', 'member:1', 'game.played', '2026-11-04 10:00', 5);

        $later = new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, clock: $kit->clock, celebration: 'confetti', celebrateOn: CelebrateOn::ScoreChange, leads: $kit->leads);
        $lost = $later->celebration('games', 'member:2');
        $taken = $later->celebration('games', 'member:1');

        self::assertNull($lost, 'A lead lost is no longer celebrated.');
        self::assertSame(Summit::Lead, $taken?->summit);
        self::assertNull(new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, clock: $kit->clock, celebration: 'confetti', celebrateOn: CelebrateOn::ScoreChange)->celebration('games', 'member:1'), 'Without the leads, nobody took one.');
        self::assertNull(new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, clock: $kit->clock, celebrateOn: CelebrateOn::ScoreChange, leads: $kit->leads)->celebration('games', 'member:1'));
        self::assertNull(new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, clock: $kit->clock, celebration: 'confetti', leads: $kit->leads)->celebration('games', 'member:1'), 'At the close, a lead is not celebrated.');
    }

    public function testALeadOfAMemberWithoutScoreIsNotCelebrated(): void
    {
        $kit = new Kit(Boards::fromArray([
            'games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month'],
        ]), '2026-11-10 12:00 UTC');
        $kit->leads->claim(new Lead('games', '2026-11', ResourceReference::fromString('member:9'), new DateTimeImmutable('2026-11-02 10:00 UTC')));

        self::assertNull(new LeaderboardsExtension($kit->leaderboards, 'ranks', clock: $kit->clock, celebration: 'confetti', celebrateOn: CelebrateOn::ScoreChange, leads: $kit->leads)->celebration('games', 'member:9'));
    }

    public function testAMomentInNoPeriodCelebratesNoLead(): void
    {
        $kit = new Kit(Boards::fromArray([
            'summer' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'season', 'seasons' => [['code' => 'summer-2026', 'start' => '2026-06-21', 'end' => '2026-09-22']]],
        ]), '2026-11-10 12:00 UTC');

        self::assertNull(new LeaderboardsExtension($kit->leaderboards, 'ranks', clock: $kit->clock, celebration: 'confetti', celebrateOn: CelebrateOn::ScoreChange, leads: $kit->leads)->celebration('summer', 'member:1'));
    }

    public function testAPromotionIsCelebratedWhenTheLeadIs(): void
    {
        $kit = new Kit(Boards::fromArray([
            'weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver'], 'promoted' => 1, 'relegated' => 0]],
        ]), '2026-11-03 12:00 UTC', $this->dispatcher());
        $kit->record('k1', 'member:1', 'game.played', '2026-11-03 10:00', 5);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-03 11:00', 3);
        $kit->clock->set('2026-11-10 12:00 UTC');
        $kit->leagues->settleDue();

        $extension = new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, clock: $kit->clock, celebration: 'confetti', celebrateOn: CelebrateOn::ScoreChange, leads: $kit->leads);

        self::assertSame([Summit::HighestTier, '2026-w45', 'silver'], [$extension->celebration('weekly', 'member:1')?->summit, $extension->celebration('weekly', 'member:1')?->period, $extension->celebration('weekly', 'member:1')?->tier]);
        self::assertNull($extension->celebration('weekly', 'member:2'));
    }

    /**
     * @return EventDispatcherInterface&object{events: list<object>}
     */
    private function dispatcher(): EventDispatcherInterface
    {
        return new class implements EventDispatcherInterface {
            /**
             * @var list<object>
             */
            public array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };
    }

    private function placing(int $rank, string $member = 'member:1'): Placing
    {
        return new Placing('games', '2026-10', ResourceReference::fromString($member), $rank, 10, new DateTimeImmutable('2026-10-20 UTC'));
    }

    private function membership(string $tier, ?Movement $movement, string $member = 'member:1'): Membership
    {
        return new Membership('weekly', '2026-w45', ResourceReference::fromString($member), $tier, 1, new DateTimeImmutable('2026-11-03 UTC'), $movement);
    }
}
