<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Override;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Leaderboards;
use Tilttek\Leaderboards\League\Leagues;
use Tilttek\Leaderboards\Row;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;
use Tilttek\LeaderboardsBundle\Infrastructure\Scheduler\TakeDue;
use Tilttek\LeaderboardsBundle\Tests\Functional\App\Recorder;
use Tilttek\LeaderboardsBundle\Tests\Functional\App\TestKernel;
use Tilttek\LeaderboardsBundle\TilttekLeaderboardsBundle;
use Twig\Environment;

/**
 * The bundle in an application: the tables in the schema the ORM generates, the scores kept up to date live by the
 * journal's projections, a cancellation, a value reported, a standing rendered by its template with each member's
 * choice, the snapshot command and its event, a weekly league over two weeks with its division rendered, its
 * settlement by the command and by the scheduled message, and a member exported and erased through the privacy bundle.
 */
#[CoversClass(TilttekLeaderboardsBundle::class)]
final class LeaderboardsBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        $this->boot('test');
    }

    public function testTheMemberWhoTakesTheLeadIsToldAndCelebrated(): void
    {
        $this->boot('lead');
        $journal = $this->service(Journal::class, Journal::class);
        $this->service(Leaderboards::class, Leaderboards::class)->appear(ResourceReference::of('member', '2'), Appearance::pseudonym('Gris'));
        $journal->record(Activity::of('g1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-02 20:00:00 UTC'), quantity: 2));
        $journal->record(Activity::of('g2', 'member:2', 'game.played', new DateTimeImmutable('2026-11-03 20:00:00 UTC'), quantity: 3));
        $journal->record(Activity::of('g3', 'member:2', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC'), quantity: 3));

        self::assertSame(['top cup ever member:2 lead pseudonym', 'top games-month 2026-11 member:2 lead pseudonym', 'top weekly 2026-w45 member:2 lead pseudonym'], $this->service(Recorder::class, Recorder::class)->events);

        $twig = $this->service('twig', Environment::class);

        if (null === TestKernel::kit()) {
            return;
        }

        $html = $twig->createTemplate("{{ include('@TilttekLeaderboards/celebration.html.twig', {celebration: tilttek_leaderboards_celebration('games-month', 'member:2')}) }}")->render();
        self::assertStringContainsString('data-summit="lead"', $html);
        self::assertStringContainsString('data-period="2026-11"', $html);
        self::assertStringContainsString('You took the lead on boards.games-month!', $html);
        self::assertSame('', trim($twig->createTemplate("{{ include('@TilttekLeaderboards/celebration.html.twig', {celebration: tilttek_leaderboards_celebration('games-month', 'member:1')}) }}")->render()));
    }

    private function boot(string $environment): void
    {
        self::ensureKernelShutdown();
        self::bootKernel(['environment' => $environment]);
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        $schema = new SchemaTool($entityManager)->getSchemaFromMetadata([]);

        foreach ([LeaderboardsTables::CONTRIBUTIONS, LeaderboardsTables::SCORES, LeaderboardsTables::SNAPSHOTS, LeaderboardsTables::PLACINGS, LeaderboardsTables::APPEARANCES, LeaderboardsTables::LEAGUE_TIERS, LeaderboardsTables::LEAGUE_MEMBERSHIPS, LeaderboardsTables::LEAGUE_WEEKS] as $table) {
            self::assertTrue($schema->hasTable($table));
        }

        foreach ($schema->toSql($entityManager->getConnection()->getDatabasePlatform()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testTheActivityRanksTheMembers(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $leaderboards = $this->service(Leaderboards::class, Leaderboards::class);

        $journal->record(Activity::of('g1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-02 20:00:00 UTC'), quantity: 4));
        $journal->record(Activity::of('g2', 'member:2', 'game.played', new DateTimeImmutable('2026-11-03 20:00:00 UTC'), quantity: 6));
        $journal->record(Activity::of('g3', 'member:3', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC'), quantity: 5));
        $journal->record(Activity::of('g4', 'member:1', 'game.played', new DateTimeImmutable('2026-11-05 20:00:00 UTC'), quantity: 3));
        $journal->cancel('g2');

        $standing = $leaderboards->standing('games-month', ResourceReference::of('member', '3'));
        self::assertSame(['1:7', '2:5'], array_map(static fn (Row $row): string => $row->rank.':'.$row->value, $standing->rows));
        self::assertSame([2, 2], [$standing->participants, $standing->own?->rank]);

        self::assertTrue($leaderboards->report('level', ResourceReference::of('member', '1'), 12));
        self::assertSame(12, $leaderboards->rank('level', ResourceReference::of('member', '1'))?->value);
    }

    public function testAStandingIsRenderedByEachMembersChoice(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $leaderboards = $this->service(Leaderboards::class, Leaderboards::class);
        $journal->record(Activity::of('g1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-02 20:00:00 UTC'), quantity: 9));
        $journal->record(Activity::of('g2', 'member:2', 'game.played', new DateTimeImmutable('2026-11-03 20:00:00 UTC'), quantity: 6));
        $journal->record(Activity::of('g3', 'member:3', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC'), quantity: 5));
        $journal->record(Activity::of('g4', 'member:4', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC'), quantity: 1));

        $leaderboards->appear(ResourceReference::of('member', '1'), Appearance::named());
        $leaderboards->appear(ResourceReference::of('member', '2'), Appearance::pseudonym('Gris'));

        $html = $this->service('twig', Environment::class)->createTemplate(
            "{{ include('@TilttekLeaderboards/standing.html.twig', {standing: tilttek_leaderboards_standing('games-month', 'member:4', limit: 3), names: {'member:1': 'Alice'}}) }}",
        )->render();
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));

        self::assertSame('boards.games-month Period 2026-11 · 4 members ranked 1 Alice 9 2 Gris 6 3 A member 5 4 You 1', $text);
        self::assertStringContainsString('tilttek-leaderboards__row--self', $html);

        $empty = $this->service('twig', Environment::class)->createTemplate(
            "{{ include('@TilttekLeaderboards/standing.html.twig', {standing: tilttek_leaderboards_standing('games-month', period: '2026-10')}) }}",
        )->render();
        self::assertSame('boards.games-month Period 2026-10 · Nobody ranked yet Nobody is ranked yet.', trim((string) preg_replace('/\s+/', ' ', strip_tags($empty))));
    }

    public function testTheSnapshotCommandFreezesTheMonthAndAnnouncesIt(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $journal->record(Activity::of('g1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-02 20:00:00 UTC'), quantity: 9));
        $journal->record(Activity::of('g2', 'member:2', 'game.played', new DateTimeImmutable('2026-11-03 20:00:00 UTC'), quantity: 6));
        $journal->record(Activity::of('g3', 'member:3', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC'), quantity: 5));
        $this->service('clock', MockClock::class)->modify('2026-12-01 06:00:00 UTC');

        $kernel = self::$kernel ?? throw new LogicException('The kernel is not booted.');
        $tester = new CommandTester(new Application($kernel)->find('tilttek:leaderboards:snapshot'));

        self::assertSame(0, $tester->execute([]));
        self::assertSame(['snapshot games-month 2026-11 3', 'top games-month 2026-11 member:1 first_place hidden', 'snapshot weekly 2026-w48 0'], $this->service(Recorder::class, Recorder::class)->events);

        $html = $this->service('twig', Environment::class)->createTemplate(
            "{{ include('@TilttekLeaderboards/standing.html.twig', {standing: tilttek_leaderboards_frozen('games-month', '2026-11', 'member:2')}) }}",
        )->render();
        self::assertSame('boards.games-month Period 2026-11 · 3 members ranked · Final standing 1 A member 9 2 You 6', trim((string) preg_replace('/\s+/', ' ', strip_tags($html))));
    }

    public function testAWeeklyLeagueOverTwoWeeks(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $journal->record(Activity::of('w1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-03 20:00:00 UTC'), quantity: 9));
        $journal->record(Activity::of('w2', 'member:2', 'game.played', new DateTimeImmutable('2026-11-03 21:00:00 UTC'), quantity: 6));
        $journal->record(Activity::of('w3', 'member:3', 'game.played', new DateTimeImmutable('2026-11-04 20:00:00 UTC'), quantity: 5));

        $html = $this->service('twig', Environment::class)->createTemplate(
            "{{ include('@TilttekLeaderboards/division.html.twig', {division: tilttek_leaderboards_division('weekly', 'member:2')}) }}",
        )->render();

        self::assertSame('boards.weekly tiers.bronze, division 1 · Period 2026-w45 · The first goes up · Nobody goes down 1 A member 9 Going up 2 You 6 Staying', trim((string) preg_replace('/\s+/', ' ', strip_tags($html))));
        self::assertStringContainsString('tilttek-leaderboards__row--promoted', $html);

        $this->service('clock', MockClock::class)->modify('2026-11-09 06:00:00 UTC');
        $kernel = self::$kernel ?? throw new LogicException('The kernel is not booted.');
        $tester = new CommandTester(new Application($kernel)->find('tilttek:leaderboards:settle'));

        self::assertSame(0, $tester->execute([]));
        self::assertSame(['settled weekly 2026-w45 [member:1>promoted member:2>stayed member:3>promoted]', 'top weekly 2026-w45 member:1 highest_tier hidden', 'top weekly 2026-w45 member:3 highest_tier hidden'], $this->service(Recorder::class, Recorder::class)->events);

        $this->service('clock', MockClock::class)->modify('2026-11-10 21:00:00 UTC');
        $journal->record(Activity::of('x1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-10 20:00:00 UTC'), quantity: 1));
        $journal->record(Activity::of('x3', 'member:3', 'game.played', new DateTimeImmutable('2026-11-10 20:30:00 UTC'), quantity: 2));
        $this->service('clock', MockClock::class)->modify('2026-11-16 06:00:00 UTC');
        $this->service('messenger.default_bus', MessageBusInterface::class)->dispatch(new TakeDue());

        self::assertSame([
            'settled weekly 2026-w45 [member:1>promoted member:2>stayed member:3>promoted]',
            'top weekly 2026-w45 member:1 highest_tier hidden',
            'top weekly 2026-w45 member:3 highest_tier hidden',
            'snapshot games-month 2026-10 0',
            'snapshot weekly 2026-w46 2',
            'top weekly 2026-w46 member:3 first_place hidden',
            'settled weekly 2026-w46 [member:3>stayed member:1>relegated]',
        ], $this->service(Recorder::class, Recorder::class)->events);
        self::assertSame('bronze', $this->service(Leagues::class, Leagues::class)->tier('weekly', ResourceReference::of('member', '1')));
    }

    public function testTheLeagueScreenIsBuiltOfTheKitsRecipes(): void
    {
        if (null === TestKernel::kit()) {
            self::markTestSkipped('The PHIL UX kit is neither beside the monorepo nor installed: its recipes cannot be rendered.');
        }

        $journal = $this->service(Journal::class, Journal::class);
        $leaderboards = $this->service(Leaderboards::class, Leaderboards::class);
        $this->service('clock', MockClock::class)->modify('2026-11-03 21:00:00 UTC');
        $journal->record(Activity::of('g1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-03 20:00:00 UTC'), quantity: 9));
        $journal->record(Activity::of('g2', 'member:2', 'game.played', new DateTimeImmutable('2026-11-03 20:10:00 UTC'), quantity: 6));
        $this->service('clock', MockClock::class)->modify('2026-11-10 21:00:00 UTC');
        $journal->record(Activity::of('x1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-10 20:00:00 UTC'), quantity: 1));
        $leaderboards->appear(ResourceReference::of('member', '1'), Appearance::pseudonym('Gris'));
        $twig = $this->service('twig', Environment::class);

        $html = $twig->createTemplate("{{ include('@TilttekLeaderboards/league.html.twig', {league: tilttek_leaderboards_league('weekly', 'member:1'), appearance_url: '/me'}) }}")->render();
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));

        self::assertStringContainsString('data-slot="card"', $html);
        self::assertStringContainsString('data-slot="badge"', $html);
        self::assertStringContainsString('aria-current="step"', $html);
        self::assertStringContainsString('datetime="2026-11-16T05:00:00+00:00"', $html);
        self::assertSame('boards.weekly Your tier: tiers.silver · the week ends 2026-11-16 00:00 tiers.silver tiers.bronze Last week, in tiers.bronze: Going up boards.weekly tiers.silver, division 1 · Period 2026-w46 · Nobody goes up · Nobody goes down 1 You 1 Staying The members of your division see you as Gris. Change how you appear', $text);

        $waiting = $twig->createTemplate("{{ include('@TilttekLeaderboards/league.html.twig', {league: tilttek_leaderboards_league('weekly', 'member:2')}) }}")->render();

        self::assertStringContainsString('data-slot="empty"', $waiting);
        self::assertStringContainsString('You are not in a division this week Score once this week to join a division of about 2 members.', trim((string) preg_replace('/\s+/', ' ', strip_tags($waiting))));
        self::assertStringContainsString('The members of your division see your score, not your name.', $waiting);
        self::assertStringNotContainsString('Change how you appear', $waiting);

        $teams = $twig->createTemplate("{{ include('@TilttekLeaderboards/teams.html.twig', {board: 'cup', rows: tilttek_leaderboards_teams('cup', {red: ['member:1'], blue: ['member:2']})}) }}")->render();

        self::assertStringContainsString('data-team="red"', $teams);
        self::assertStringContainsString('Luck', $teams);
        self::assertStringContainsString('team_names.blue', $teams);

        $celebration = $twig->createTemplate("{{ include('@TilttekLeaderboards/celebration.html.twig', {celebration: tilttek_leaderboards_celebration('weekly', 'member:1')}) }}")->render();

        self::assertStringContainsString('data-controller="celebration"', $celebration);
        self::assertStringContainsString('data-celebration-autoplay-value="true"', $celebration);
        self::assertStringContainsString('data-celebration-effect-value="confetti"', $celebration);
        self::assertStringContainsString('data-summit="highest_tier"', $celebration);
        self::assertStringContainsString('role="status" aria-live="polite"', $celebration);
        self::assertStringContainsString('You reached tiers.silver, the highest tier of boards.weekly!', $celebration);
        self::assertSame('', trim($twig->createTemplate("{{ include('@TilttekLeaderboards/celebration.html.twig', {celebration: tilttek_leaderboards_celebration('weekly', 'member:2')}) }}")->render()));

        $later = $twig->createTemplate("{% use '@TilttekLeaderboards/celebration.html.twig' %}{% set celebration = tilttek_leaderboards_celebration('weekly', 'member:1') %}{% set autoplay = false %}{{ block('tilttek_leaderboards_celebration') }}")->render();

        self::assertStringContainsString('data-celebration-autoplay-value="false"', $later);
    }

    public function testAMemberIsExportedAndErasedWithTheirAccount(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $leaderboards = $this->service(Leaderboards::class, Leaderboards::class);
        $journal->record(Activity::of('g1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-02 20:00:00 UTC'), quantity: 4));
        $leaderboards->appear(ResourceReference::of('member', '1'), Appearance::named());

        $export = $this->service(SubjectExport::class, SubjectExport::class)->export(DataSubject::fromString('1'));
        self::assertSame(['visibility' => 'named', 'pseudonym' => null], $export['tilttek_leaderboards']['appearance'] ?? null);

        $this->service(SubjectErasure::class, SubjectErasure::class)->erase(DataSubject::fromString('1'));

        self::assertSame(0, $leaderboards->standing('games-month')->participants);
        self::assertEquals(Appearance::hidden(), $leaderboards->appearance(ResourceReference::of('member', '1')));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
