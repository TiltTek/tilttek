<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Activity\Activity;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\Summit;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\TopReached;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\InMemoryLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Lead;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\LeadWatch;
use Tilttek\LeaderboardsBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LeaderboardsBundle\Tests\Unit\Fixture\Kit;
use Tilttek\LeaderboardsBundle\Tests\Unit\Fixture\RacingScoreStore;

#[CoversClass(LeadWatch::class)]
#[CoversClass(Lead::class)]
#[CoversClass(TopReached::class)]
#[CoversClass(Summit::class)]
final class LeadWatchTest extends TestCase
{
    public function testTheMemberWhoTakesTheLeadIsToldOnce(): void
    {
        $dispatcher = $this->dispatcher();
        $kit = $this->kit($dispatcher);

        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', 5);
        self::assertSame([], $dispatcher->events, 'Alone on the board, nobody is behind.');

        $kit->record('k2', 'member:2', 'game.played', '2026-11-02 11:00', 3);
        self::assertSame([], $dispatcher->events, 'A member behind does not take the lead.');

        $kit->record('k3', 'member:2', 'game.played', '2026-11-03 11:00', 4);
        $kit->record('k4', 'member:2', 'game.played', '2026-11-03 12:00', 1);
        $kit->record('k5', 'member:1', 'game.played', '2026-11-04 10:00', 5);
        $kit->record('k6', 'member:2', 'game.played', '2026-11-05 10:00', 9);
        $kit->record('k7', 'member:1', 'game.played', '2026-11-06 10:00', 9);

        self::assertEquals([
            new TopReached('games', '2026-11', ResourceReference::fromString('member:2'), Summit::Lead, new DateTimeImmutable('2026-11-03 11:00 UTC')),
            new TopReached('games', '2026-11', ResourceReference::fromString('member:1'), Summit::Lead, new DateTimeImmutable('2026-11-04 10:00 UTC')),
        ], $dispatcher->events, 'Taking the lead back, or scoring while leading, tells nothing new.');
        self::assertTrue($kit->leads->has('games', '2026-11', ResourceReference::fromString('member:2')));
        self::assertEquals([new Lead('games', '2026-11', ResourceReference::fromString('member:1'), new DateTimeImmutable('2026-11-04 10:00 UTC'))], $kit->leads->leads(ResourceReference::fromString('member:1')));
    }

    public function testTheEventCarriesHowTheMemberChoseToAppear(): void
    {
        $dispatcher = $this->dispatcher();
        $kit = $this->kit($dispatcher);
        $kit->leaderboards->appear(ResourceReference::fromString('member:2'), Appearance::pseudonym('Le Nain Gris'));
        $kit->leaderboards->appear(ResourceReference::fromString('member:3'), Appearance::named());

        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', 1);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-02 11:00', 2);
        $kit->record('k3', 'member:3', 'game.played', '2026-11-02 12:00', 3);
        $kit->record('k4', 'member:1', 'game.played', '2026-11-02 13:00', 9);

        self::assertEquals(
            [Appearance::pseudonym('Le Nain Gris'), Appearance::named(), Appearance::hidden()],
            array_map(static fn (object $event): Appearance => $event instanceof TopReached ? $event->appearance : Appearance::named(), $dispatcher->events),
        );
    }

    public function testAReportedScoreThatTakesTheLeadIsTold(): void
    {
        $dispatcher = $this->dispatcher();
        $kit = $this->kit($dispatcher, Boards::fromArray(['streak' => ['measure' => 'highest', 'period' => 'month']]));

        self::assertTrue($kit->leaderboards->report('streak', ResourceReference::fromString('member:1'), 5, new DateTimeImmutable('2026-11-02 10:00 UTC')));
        self::assertTrue($kit->leaderboards->report('streak', ResourceReference::fromString('member:2'), 7, new DateTimeImmutable('2026-11-03 10:00 UTC')));
        self::assertFalse($kit->leaderboards->report('streak', ResourceReference::fromString('member:1'), 4, new DateTimeImmutable('2026-11-04 10:00 UTC')));

        self::assertEquals([new TopReached('streak', '2026-11', ResourceReference::fromString('member:2'), Summit::Lead, new DateTimeImmutable('2026-11-03 10:00 UTC'))], $dispatcher->events);
    }

    public function testAReportTheStoreDoesNotKeepTakesNoLead(): void
    {
        $dispatcher = $this->dispatcher();
        $boards = Boards::fromArray(['streak' => ['measure' => 'highest', 'period' => 'month']]);
        $inner = new InMemoryScoreStore();
        $inner->save(new Score('streak', '2026-11', ResourceReference::fromString('member:1'), 5, new DateTimeImmutable('2026-11-02 10:00 UTC')));
        $inner->save(new Score('streak', '2026-11', ResourceReference::fromString('member:2'), 9, new DateTimeImmutable('2026-11-03 10:00 UTC')));

        $racer = new Score('streak', '2026-11', ResourceReference::fromString('member:1'), 12, new DateTimeImmutable('2026-11-04 10:00 UTC'));
        $leads = new InMemoryLeadStore();
        $watch = new LeadWatch(new RacingScoreStore($inner, $racer), $boards, $leads, $dispatcher, new Clock('2026-11-07 12:00 UTC'));

        self::assertFalse($watch->keep(new Score('streak', '2026-11', ResourceReference::fromString('member:1'), 10, new DateTimeImmutable('2026-11-04 10:00 UTC')), Measure::Highest, $boards->get('streak')->ranking));
        self::assertSame([], $dispatcher->events);
        self::assertSame([], $leads->leads(ResourceReference::fromString('member:1')));
        self::assertSame(12, $inner->score('streak', '2026-11', ResourceReference::fromString('member:1'))?->value);
    }

    public function testAReportOnAPeriodOverIsKeptUnwatched(): void
    {
        $dispatcher = $this->dispatcher();
        $kit = $this->kit($dispatcher, Boards::fromArray(['streak' => ['measure' => 'highest', 'period' => 'month']]));

        self::assertTrue($kit->leaderboards->report('streak', ResourceReference::fromString('member:1'), 5, new DateTimeImmutable('2026-10-02 10:00 UTC')));
        self::assertTrue($kit->leaderboards->report('streak', ResourceReference::fromString('member:2'), 7, new DateTimeImmutable('2026-10-03 10:00 UTC')));
        self::assertFalse($kit->leaderboards->report('streak', ResourceReference::fromString('member:2'), 6, new DateTimeImmutable('2026-10-04 10:00 UTC')));

        self::assertSame([], $dispatcher->events);
        self::assertSame(7, $kit->scores->score('streak', '2026-10', ResourceReference::fromString('member:2'))?->value);
    }

    public function testAPeriodOverIsNotWatched(): void
    {
        $dispatcher = $this->dispatcher();
        $kit = $this->kit($dispatcher);

        $kit->record('k1', 'member:1', 'game.played', '2026-10-30 10:00', 1);
        $kit->record('k2', 'member:2', 'game.played', '2026-10-30 11:00', 2);

        self::assertSame([], $dispatcher->events);
        self::assertSame([], $kit->leads->leads(ResourceReference::fromString('member:2')));
        self::assertSame(2, $kit->leaderboards->standing('games', period: '2026-10')->participants, 'The scores of a period over are kept all the same.');
    }

    public function testARebuildTellsNothingNew(): void
    {
        $dispatcher = $this->dispatcher();
        $kit = $this->kit($dispatcher);
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', 1);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-02 11:00', 2);

        $kit->projector->rebuild($kit->projection);

        self::assertCount(1, $dispatcher->events);
        self::assertSame(2, $kit->leaderboards->rank('games', ResourceReference::fromString('member:2'))?->value);
    }

    public function testASharedFirstPlaceIsALead(): void
    {
        $dispatcher = $this->dispatcher();
        $kit = $this->kit($dispatcher, Boards::fromArray(['games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month', 'ties' => 'shared']]));

        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', 3);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-02 11:00', 3);

        self::assertEquals([new TopReached('games', '2026-11', ResourceReference::fromString('member:2'), Summit::Lead, new DateTimeImmutable('2026-11-02 11:00 UTC'))], $dispatcher->events);
    }

    public function testAnEqualScoreReachedLaterIsNotALeadWhereTheFirstRanksFirst(): void
    {
        $dispatcher = $this->dispatcher();
        $kit = $this->kit($dispatcher);

        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', 3);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-02 11:00', 3);

        self::assertSame([], $dispatcher->events);
    }

    public function testABoardRankedByObjectIsWatchedUnderItsKey(): void
    {
        $dispatcher = $this->dispatcher();
        $kit = $this->kit($dispatcher, Boards::fromArray(['speed' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month', 'per_object' => true]]));
        $catan = ResourceReference::fromString('game:catan');
        $key = $kit->boards->get('speed')->key($catan);

        foreach ([['k1', 'member:1', 1], ['k2', 'member:2', 2]] as [$activity, $member, $quantity]) {
            $kit->journal->record(Activity::of($activity, $member, 'game.played', new DateTimeImmutable('2026-11-02 10:00 UTC'), $catan->toString(), $quantity));
        }

        self::assertEquals([new TopReached($key, '2026-11', ResourceReference::fromString('member:2'), Summit::Lead, new DateTimeImmutable('2026-11-02 10:00 UTC'))], $dispatcher->events);
    }

    public function testTheLeaderIsKnownByTheMemberWhenTheStoreKeepsMomentsToTheSecond(): void
    {
        $dispatcher = $this->dispatcher();
        $connection = Database::connection();
        $watch = new LeadWatch(new DbalScoreStore($connection), Boards::fromArray(['games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month']]), new DbalLeadStore($connection), $dispatcher, new Clock('2026-11-07 12:00 UTC'));

        $watch->save(new Score('games', '2026-11', ResourceReference::fromString('member:1'), 1, new DateTimeImmutable('2026-11-07 10:00:00.250 UTC')));
        $watch->save(new Score('games', '2026-11', ResourceReference::fromString('member:2'), 2, new DateTimeImmutable('2026-11-07 10:00:01.750 UTC')));
        $watch->save(new Score('games', '2026-11', ResourceReference::fromString('member:2'), 3, new DateTimeImmutable('2026-11-07 10:00:02.750 UTC')));

        self::assertCount(1, $dispatcher->events, "The score read back to the second is still the member's own.");
        self::assertTrue(new DbalLeadStore($connection)->has('games', '2026-11', ResourceReference::fromString('member:2')));
    }

    public function testAScoreOfABoardNoLongerDeclaredIsKeptUnwatched(): void
    {
        $dispatcher = $this->dispatcher();
        $scores = new InMemoryScoreStore();
        $watch = new LeadWatch($scores, Boards::fromArray([]), new InMemoryLeadStore(), $dispatcher, new Clock('2026-11-07 12:00 UTC'));

        $watch->save(new Score('gone', 'ever', ResourceReference::fromString('member:1'), 1, new DateTimeImmutable('2026-11-07 10:00 UTC')));
        $watch->save(new Score('gone', 'ever', ResourceReference::fromString('member:2'), 2, new DateTimeImmutable('2026-11-07 11:00 UTC')));

        self::assertSame([], $dispatcher->events);
        self::assertSame(2, $scores->score('gone', 'ever', ResourceReference::fromString('member:2'))?->value);
    }

    public function testAMomentInNoPeriodIsNotWatched(): void
    {
        $dispatcher = $this->dispatcher();
        $boards = Boards::fromArray(['summer' => ['measure' => 'latest', 'period' => 'season', 'seasons' => [['code' => 'summer-2026', 'start' => '2026-06-21', 'end' => '2026-09-22']]]]);
        $scores = new InMemoryScoreStore();
        $watch = new LeadWatch($scores, $boards, new InMemoryLeadStore(), $dispatcher, new Clock('2026-11-07 12:00 UTC'));

        $watch->save(new Score('summer', 'summer-2026', ResourceReference::fromString('member:1'), 1, new DateTimeImmutable('2026-07-07 10:00 UTC')));
        $watch->save(new Score('summer', 'summer-2026', ResourceReference::fromString('member:2'), 2, new DateTimeImmutable('2026-07-07 11:00 UTC')));

        self::assertSame([], $dispatcher->events);
        self::assertSame(2, $scores->score('summer', 'summer-2026', ResourceReference::fromString('member:2'))?->value);
    }

    private function kit(EventDispatcherInterface $dispatcher, ?Boards $boards = null): Kit
    {
        return new Kit($boards ?? Boards::fromArray(['games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month']]), '2026-11-07 12:00 UTC', $dispatcher);
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
}
