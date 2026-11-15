<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Activity\Activity;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\League\Movement;
use Tilttek\Leaderboards\League\Settlement;
use Tilttek\Leaderboards\Snapshot;
use Tilttek\Leaderboards\TeamRow;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\LeagueSettled;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\SnapshotTaken;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\InMemoryLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Lead;
use Tilttek\LeaderboardsBundle\Infrastructure\Privacy\LeaderboardsPrivacy;
use Tilttek\LeaderboardsBundle\Infrastructure\Twig\KitComponents;
use Tilttek\LeaderboardsBundle\Infrastructure\Twig\LeaderboardsExtension;
use Tilttek\LeaderboardsBundle\Infrastructure\Twig\LeagueView;
use Tilttek\LeaderboardsBundle\Tests\Unit\Fixture\Kit;

#[CoversClass(DispatchingObserver::class)]
#[CoversClass(SnapshotTaken::class)]
#[CoversClass(LeagueSettled::class)]
#[CoversClass(LeaderboardsExtension::class)]
#[CoversClass(LeagueView::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(LeaderboardsPrivacy::class)]
final class IntegrationsTest extends TestCase
{
    public function testTheObserverDispatchesAnEvent(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
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
        $snapshot = new Snapshot('games', '2026-10', new DateTimeImmutable('2026-11-01 UTC'), 0, []);

        new DispatchingObserver($dispatcher)->snapshotTaken($snapshot);

        self::assertEquals([new SnapshotTaken($snapshot)], $dispatcher->events);
    }

    public function testTheObserverDispatchesASettlement(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
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
        $settlement = new Settlement('weekly', '2026-w45', new DateTimeImmutable('2026-11-09 UTC'), []);

        new DispatchingObserver($dispatcher)->leagueSettled($settlement);

        self::assertEquals([new LeagueSettled($settlement)], $dispatcher->events);
    }

    public function testTheDivisionInTwig(): void
    {
        $kit = $this->kit();
        $extension = new LeaderboardsExtension($kit->leaderboards, 'ranks');

        self::assertSame(['bronze', 2], [$extension->division('weekly', 'member:2')?->membership->tier, $extension->division('weekly', 'member:2')?->members]);
        self::assertSame(1, $extension->division('weekly', 'member:1', '2026-w45')?->standing->own?->rank);
        self::assertNull($extension->division('weekly', 'member:1', '2026-w44'));
        self::assertNull($extension->division('weekly', 'member:9'));
    }

    public function testTheLeaguesAreExported(): void
    {
        $kit = $this->kit();
        $privacy = new LeaderboardsPrivacy($kit->leaderboards, 'member');
        $kit->journal->cancel('k2');
        $kit->leagueStore->settle(new Settlement('weekly', '2026-w44', new DateTimeImmutable('2026-11-02 UTC'), []), ['member:5' => 'silver']);

        self::assertSame([], $privacy->export(DataSubject::fromString('2'))['scores'] ?? null);
        self::assertSame('bronze', $privacy->export(DataSubject::fromString('2'))['leagues']['memberships'][0]['tier'] ?? null);
        self::assertSame(['weekly' => 'silver'], $privacy->export(DataSubject::fromString('5'))['leagues']['tiers'] ?? null);
    }

    public function testTheTwigFunctions(): void
    {
        $kit = $this->kit();
        $extension = new LeaderboardsExtension($kit->leaderboards, 'ranks');
        $kit->leaderboards->appear(ResourceReference::fromString('member:2'), Appearance::pseudonym('Gris'));
        $kit->clock->set('2026-12-01 05:00 UTC');
        $kit->leaderboards->snapshot('games', '2026-11');

        self::assertSame('ranks', $extension->domain());

        $standing = $extension->standing('games', 'member:1', '2026-11');
        self::assertSame([2, 1], [\count($standing->rows), $standing->own?->rank]);
        self::assertNull($extension->standing('games', period: '2026-11')->own);
        self::assertCount(1, $extension->standing('games', 'member:1', '2026-11', 1)->rows);
        self::assertSame(1, $extension->standing('games', 'member:2', '2026-11', 10, ['member:2', 'member:9'])->participants);
        self::assertSame(2, $extension->standing('games', 'member:2', '2026-11', 10, [])->participants);

        $frozen = $extension->frozen('games', '2026-11', 'member:2');
        self::assertSame([2, 2], [\count($frozen->rows ?? []), $frozen?->own?->rank]);
        self::assertNull($extension->frozen('games', '2026-11')?->own);
        self::assertCount(1, $extension->frozen('games', '2026-11', 'member:2', 1)->rows ?? []);
        self::assertNull($extension->frozen('games', '2026-10'));
        self::assertEquals(Appearance::pseudonym('Gris'), $extension->appearance('member:2'));
    }

    public function testTheLeagueTeamsTitlesAndKitInTwig(): void
    {
        $kit = new Kit(Boards::fromArray([
            'weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver', 'gold'], 'promoted' => 1, 'relegated' => 0]],
            'records' => ['measure' => 'best', 'verbs' => ['game.scored'], 'per_object' => true],
            'busy' => ['measure' => 'count', 'verbs' => ['game.played'], 'title' => ['name' => 'busy', 'threshold' => 2]],
        ]), '2026-11-03 12:00 UTC');
        $kit->record('k1', 'member:1', 'game.played', '2026-11-03 10:00', 5);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-03 11:00', 3);
        $kit->clock->set('2026-11-10 12:00 UTC');
        $kit->record('k3', 'member:1', 'game.played', '2026-11-10 10:00', 1);
        $kit->journal->record(Activity::of('k4', 'member:1', 'game.scored', new DateTimeImmutable('2026-11-10 10:00 UTC'), 'game:catan', 70));

        $extension = new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, new KitComponents('ui/kit/'), $kit->clock);

        $league = $extension->league('weekly', 'member:1');

        self::assertSame('weekly', $league->board->name);
        self::assertSame(['bronze', 'silver', 'gold'], $league->tiers);
        self::assertSame(['gold', 'silver', 'bronze'], $league->ladder());
        self::assertSame('silver', $league->tier);
        self::assertSame('2026-w46', $league->division?->membership->week);
        self::assertSame('2026-11-16 00:00', $league->weekEndsAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'));
        self::assertSame([Movement::Promoted, 'bronze'], [$league->lastWeek?->movement, $league->lastWeek?->tier]);
        self::assertEquals(Appearance::hidden(), $league->appearance);

        $waiting = $extension->league('weekly', 'member:2');
        self::assertNull($waiting->division);
        self::assertSame('bronze', $waiting->tier);
        self::assertSame(Movement::Stayed, $waiting->lastWeek?->movement);
        self::assertNull($extension->league('weekly', 'member:9')->lastWeek);

        self::assertSame('ui:kit:Badge', $extension->kit('Badge'));
        self::assertSame('Card:Header', new LeaderboardsExtension($kit->leaderboards, 'ranks')->kit('Card:Header'));
        self::assertSame('Badge', new KitComponents(null)->name('Badge'));
        self::assertSame(['busy'], $extension->titles('member:1'));
        self::assertTrue($extension->holds('busy', 'member:1'));
        self::assertFalse($extension->holds('busy', 'member:2'));
        self::assertFalse($extension->holds('records', 'member:1', 'game:catan'));
        self::assertSame([], $extension->titles('member:2'));
        self::assertEquals([new TeamRow(1, 'red', 1, 2, 0, 2), new TeamRow(2, 'blue', 1, 1, 0, 1)], $extension->teams('busy', ['blue' => ['member:2'], 'red' => ['member:1', 'member:3']]));
        self::assertEquals([new TeamRow(1, 'red', 1, 70, 0, 70)], $extension->teams('records', ['red' => ['member:1']], object: 'game:catan'));
        self::assertSame(70, $extension->standing('records', 'member:1', object: 'game:catan')->own?->value);
    }

    public function testTheLeagueScreenIsDatedByTheClockOrByTheTime(): void
    {
        $kit = new Kit(Boards::fromArray(['weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver']]]]), '2000-01-05 12:00 UTC');
        $kit->record('k1', 'member:1', 'game.played', '2000-01-05 10:00');

        $dated = new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues, clock: $kit->clock)->league('weekly', 'member:1');
        self::assertSame('2000-01-10 00:00', $dated->weekEndsAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'));

        $now = new LeaderboardsExtension($kit->leaderboards, 'ranks', $kit->leagues)->league('weekly', 'member:1');
        self::assertGreaterThan(new DateTimeImmutable('2026-01-01 UTC'), $now->weekEndsAt);
    }

    public function testTheLeagueNeedsTheLeagues(): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains('The leaderboards are not given their leagues.');

        new LeaderboardsExtension($this->kit()->leaderboards, 'ranks')->league('weekly', 'member:1');
    }

    public function testAMemberIsExportedAndErased(): void
    {
        $kit = $this->kit();
        $privacy = new LeaderboardsPrivacy($kit->leaderboards, 'member');
        $kit->leaderboards->appear(ResourceReference::fromString('member:3'), Appearance::named());

        self::assertSame('tilttek_leaderboards', $privacy->module()->value);
        self::assertSame(['board' => 'games', 'period' => '2026-11', 'value' => 5, 'tie_breaks' => [], 'reached_at' => '2026-11-02T10:00:00+00:00'], $privacy->export(DataSubject::fromString('1'))['scores'][0] ?? null);
        self::assertSame([], $privacy->export(DataSubject::fromString('9')));
        self::assertSame(['visibility' => 'named', 'pseudonym' => null], $privacy->export(DataSubject::fromString('3'))['appearance'] ?? null);
        self::assertSame([], new LeaderboardsPrivacy($kit->leaderboards, 'user')->export(DataSubject::fromString('1')));

        $report = $privacy->erase(DataSubject::fromString('1'));

        self::assertSame([], $privacy->export(DataSubject::fromString('1')));
        self::assertSame('tilttek_leaderboards', $report->module->value);
        self::assertSame([
            'tilttek_leaderboards_contribution' => ErasureEffect::Deleted,
            'tilttek_leaderboards_score' => ErasureEffect::Deleted,
            'tilttek_leaderboards_placing' => ErasureEffect::Deleted,
            'tilttek_leaderboards_appearance' => ErasureEffect::Deleted,
            'tilttek_leaderboards_league_tier' => ErasureEffect::Deleted,
            'tilttek_leaderboards_league_membership' => ErasureEffect::Deleted,
            'tilttek_leaderboards_lead' => ErasureEffect::Deleted,
        ], $report->effects);
        self::assertSame(1, $kit->leaderboards->standing('games', period: '2026-11')->participants);
        self::assertEquals($report, $privacy->erase(DataSubject::fromString('1')), 'An erasure replayed reports the same effects.');
    }

    public function testTheLeadsAMemberTookAreExportedAndErased(): void
    {
        $kit = $this->kit();
        $leads = new InMemoryLeadStore();
        $privacy = new LeaderboardsPrivacy($kit->leaderboards, 'member', $leads);
        $leads->claim(new Lead('games', '2026-11', ResourceReference::fromString('member:1'), new DateTimeImmutable('2026-11-02 10:00 UTC')));
        $leads->claim(new Lead('games', '2026-10', ResourceReference::fromString('member:8'), new DateTimeImmutable('2026-10-02 10:00 UTC')));

        self::assertSame([['board' => 'games', 'period' => '2026-11', 'taken_at' => '2026-11-02T10:00:00+00:00']], $privacy->export(DataSubject::fromString('1'))['leads'] ?? null);
        self::assertSame([], $privacy->export(DataSubject::fromString('2'))['leads'] ?? null);
        self::assertSame([['board' => 'games', 'period' => '2026-10', 'taken_at' => '2026-10-02T10:00:00+00:00']], $privacy->export(DataSubject::fromString('8'))['leads'] ?? null, 'A member who holds nothing but a lead is exported.');

        $privacy->erase(DataSubject::fromString('8'));

        self::assertSame([], $privacy->export(DataSubject::fromString('8')));
        self::assertSame([], $leads->leads(ResourceReference::fromString('member:8')));
        self::assertCount(1, $leads->leads(ResourceReference::fromString('member:1')));
    }

    private function kit(): Kit
    {
        $kit = new Kit(Boards::fromArray([
            'games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month'],
            'weekly' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver']]],
        ]));
        $kit->record('k1', 'member:1', 'game.played', '2026-11-02 10:00', 5);
        $kit->record('k2', 'member:2', 'game.played', '2026-11-03 10:00', 3);
        $kit->clock->set('2026-11-07 12:00 UTC');

        return $kit;
    }
}
