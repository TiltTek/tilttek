<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\LeaderboardProjection;
use Tilttek\Leaderboards\Leaderboards;
use Tilttek\Leaderboards\League\Leagues;
use Tilttek\Leaderboards\Store\AppearanceStore;
use Tilttek\Leaderboards\Store\InMemoryAppearanceStore;
use Tilttek\Leaderboards\Store\InMemoryLeagueStore;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\Leaderboards\Store\InMemorySnapshotStore;
use Tilttek\Leaderboards\Store\LeagueStore;
use Tilttek\Leaderboards\Store\ScoreStore;
use Tilttek\Leaderboards\Store\SnapshotStore;
use Tilttek\Leaderboards\Store\Valkey\RawCommandConnection;
use Tilttek\Leaderboards\Store\Valkey\ValkeyScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Console\SettleCommand;
use Tilttek\LeaderboardsBundle\Infrastructure\Console\ShowCommand;
use Tilttek\LeaderboardsBundle\Infrastructure\Console\SlideCommand;
use Tilttek\LeaderboardsBundle\Infrastructure\Console\SnapshotCommand;
use Tilttek\LeaderboardsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\LeaderboardsBundle\Infrastructure\DependencyInjection\LeaderboardsServices;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalAppearanceStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalLeagueStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalSnapshotStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsSchemaListener;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\CelebrateOn;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\InMemoryLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\LeadWatch;
use Tilttek\LeaderboardsBundle\Infrastructure\Privacy\LeaderboardsPrivacy;
use Tilttek\LeaderboardsBundle\Infrastructure\Scheduler\LeaderboardsSchedule;
use Tilttek\LeaderboardsBundle\Infrastructure\Scheduler\TakeDue;
use Tilttek\LeaderboardsBundle\Infrastructure\Scheduler\TakeDueHandler;
use Tilttek\LeaderboardsBundle\Infrastructure\Twig\KitComponents;
use Tilttek\LeaderboardsBundle\Infrastructure\Twig\LeaderboardsExtension;
use Tilttek\LeaderboardsBundle\TilttekLeaderboardsBundle;

#[CoversClass(TilttekLeaderboardsBundle::class)]
#[CoversClass(LeaderboardsServices::class)]
final class TilttekLeaderboardsBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertSame('tilttek_leaderboards', $this->extension()->getAlias());
        self::assertSame([
            'service_container', DbalScoreStore::class, DbalSnapshotStore::class, DbalAppearanceStore::class, DbalLeagueStore::class, DbalLeadStore::class, LeaderboardsSchemaListener::class,
            Boards::class, DispatchingObserver::class, Leagues::class, Leaderboards::class, LeaderboardProjection::class, SnapshotCommand::class, ShowCommand::class,
            SettleCommand::class, SlideCommand::class, KitComponents::class, LeaderboardsExtension::class, LeaderboardsPrivacy::class,
        ], array_keys($container->getDefinitions()));

        foreach ([ScoreStore::class => DbalScoreStore::class, SnapshotStore::class => DbalSnapshotStore::class, AppearanceStore::class => DbalAppearanceStore::class, LeagueStore::class => DbalLeagueStore::class, LeadStore::class => DbalLeadStore::class] as $port => $store) {
            self::assertEquals([$connection], $container->getDefinition($store)->getArguments());
            self::assertFalse($container->getDefinition($store)->isPublic());
            self::assertSame([$store, true], [(string) $container->getAlias($port), $container->getAlias($port)->isPublic()]);
        }

        self::assertEquals([$connection], $container->getDefinition(LeaderboardsSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(LeaderboardsSchemaListener::class)->getTags());
        self::assertSame([Boards::class, 'fromArray'], $container->getDefinition(Boards::class)->getFactory());
        self::assertSame([[], 'UTC'], $container->getDefinition(Boards::class)->getArguments());
        self::assertTrue($container->getDefinition(Boards::class)->isPublic());
        self::assertEquals([new Reference(EventDispatcherInterface::class), new Reference(Boards::class), CelebrateOn::PeriodClose, new Reference(AppearanceStore::class)], $container->getDefinition(DispatchingObserver::class)->getArguments());
        self::assertFalse($container->hasDefinition(LeadWatch::class));
        self::assertEquals([new Reference(Boards::class), new Reference(ScoreStore::class), new Reference(LeagueStore::class), new Reference('clock'), new Reference(DispatchingObserver::class)], $container->getDefinition(Leagues::class)->getArguments());
        self::assertTrue($container->getDefinition(Leagues::class)->isPublic());
        self::assertEquals([new Reference(Boards::class), new Reference(ScoreStore::class), new Reference(SnapshotStore::class), new Reference(AppearanceStore::class), new Reference('clock'), new Reference(DispatchingObserver::class), new Reference(Leagues::class), '%kernel.secret%'], $container->getDefinition(Leaderboards::class)->getArguments());
        self::assertTrue($container->getDefinition(Leaderboards::class)->isPublic());
        self::assertEquals([new Reference(Boards::class), new Reference(ScoreStore::class), 'leaderboards', new Reference(Leagues::class), new Reference('clock')], $container->getDefinition(LeaderboardProjection::class)->getArguments());
        self::assertSame(['tilttek_activity.projection' => [[]]], $container->getDefinition(LeaderboardProjection::class)->getTags());
        self::assertTrue($container->getDefinition(LeaderboardProjection::class)->isPublic());

        foreach ([SnapshotCommand::class, ShowCommand::class] as $command) {
            self::assertEquals([new Reference(Leaderboards::class)], $container->getDefinition($command)->getArguments());
            self::assertSame(['console.command' => [[]]], $container->getDefinition($command)->getTags());
        }

        self::assertEquals([new Reference(Leagues::class)], $container->getDefinition(SettleCommand::class)->getArguments());
        self::assertSame(['console.command' => [[]]], $container->getDefinition(SettleCommand::class)->getTags());
        self::assertEquals([new Reference(LeaderboardProjection::class), new Reference('clock')], $container->getDefinition(SlideCommand::class)->getArguments());
        self::assertSame(['console.command' => [[]]], $container->getDefinition(SlideCommand::class)->getTags());
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());

        self::assertEquals([new Reference(Leaderboards::class), 'tilttek_leaderboards', new Reference(Leagues::class), new Reference(KitComponents::class), new Reference('clock'), 'confetti', CelebrateOn::PeriodClose, new Reference(LeadStore::class)], $container->getDefinition(LeaderboardsExtension::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(LeaderboardsExtension::class)->getTags());
        self::assertTrue($container->getDefinition(LeaderboardsExtension::class)->isPublic());
        self::assertEquals([new Reference(Leaderboards::class), 'member', new Reference(LeadStore::class)], $container->getDefinition(LeaderboardsPrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(LeaderboardsPrivacy::class)->getTags());
    }

    public function testTheCelebration(): void
    {
        self::assertSame('fireworks', $this->load(['celebration' => ['effect' => 'fireworks']])->getDefinition(LeaderboardsExtension::class)->getArgument(5));
        self::assertSame('confetti', $this->load(['celebration' => ['effect' => 'confetti']])->getDefinition(LeaderboardsExtension::class)->getArgument(5));
        self::assertNull($this->load(['celebration' => false])->getDefinition(LeaderboardsExtension::class)->getArgument(5));
        self::assertNull($this->load(['celebration' => ['enabled' => false, 'effect' => 'fireworks']])->getDefinition(LeaderboardsExtension::class)->getArgument(5));
        self::assertSame(CelebrateOn::PeriodClose, $this->load(['celebration' => false])->getDefinition(LeaderboardsExtension::class)->getArgument(6));
    }

    public function testTheFirstPlacesCelebratedAsScoresChange(): void
    {
        $container = $this->load(['celebration' => ['celebrate_on' => 'score_change']]);

        self::assertSame(CelebrateOn::ScoreChange, $container->getDefinition(LeaderboardsExtension::class)->getArgument(6));
        self::assertSame(CelebrateOn::ScoreChange, $container->getDefinition(DispatchingObserver::class)->getArgument(2));
        self::assertEquals([new Reference(DbalScoreStore::class), new Reference(Boards::class), new Reference(LeadStore::class), new Reference(EventDispatcherInterface::class), new Reference('clock'), new Reference(AppearanceStore::class)], $container->getDefinition(LeadWatch::class)->getArguments());
        self::assertSame([LeadWatch::class, true], [(string) $container->getAlias(ScoreStore::class), $container->getAlias(ScoreStore::class)->isPublic()]);
        self::assertFalse($container->getDefinition(LeadWatch::class)->isPublic());

        $valkey = $this->load(['celebration' => ['celebrate_on' => 'score_change'], 'valkey' => ['enabled' => true, 'client' => 'app.valkey']]);
        self::assertEquals(new Reference(ValkeyScoreStore::class), $valkey->getDefinition(LeadWatch::class)->getArgument(0));
        self::assertSame(LeadWatch::class, (string) $valkey->getAlias(ScoreStore::class));

        $memory = $this->load(['store' => 'memory', 'celebration' => ['enabled' => false, 'celebrate_on' => 'score_change']]);
        self::assertEquals(new Reference(InMemoryScoreStore::class), $memory->getDefinition(LeadWatch::class)->getArgument(0));
        self::assertSame(CelebrateOn::ScoreChange, $memory->getDefinition(DispatchingObserver::class)->getArgument(2));
    }

    public function testTheFirstPlacesAreCelebratedAtAKnownMoment(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load(['celebration' => ['celebrate_on' => 'midnight']]);
    }

    public function testTheCelebrationDrawsAKnownEffect(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load(['celebration' => ['effect' => 'balloons']]);
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'store' => 'memory',
            'timezone' => 'America/Toronto',
            'boards' => [
                'games-month' => ['measure' => 'count', 'verbs' => ['game.played'], 'context' => ['ranked' => true], 'period' => 'month', 'ties' => 'shared', 'snapshot_size' => 10],
                'summer' => ['measure' => 'total', 'verbs' => ['quest.*'], 'period' => 'season', 'timezone' => 'Europe/Paris', 'seasons' => [['code' => 'summer-2026', 'start' => '2026-06-21', 'end' => '2026-09-22']]],
                'level' => ['measure' => 'latest'],
            ],
            'projection' => 'ranks',
            'translation_domain' => 'ranks',
            'privacy' => ['member_kind' => 'user'],
        ]);

        self::assertSame([
            'service_container', InMemoryScoreStore::class, InMemorySnapshotStore::class, InMemoryAppearanceStore::class, InMemoryLeagueStore::class, InMemoryLeadStore::class,
            Boards::class, DispatchingObserver::class, Leagues::class, Leaderboards::class, LeaderboardProjection::class, SnapshotCommand::class, ShowCommand::class,
            SettleCommand::class, SlideCommand::class, KitComponents::class, LeaderboardsExtension::class, LeaderboardsPrivacy::class,
        ], array_keys($container->getDefinitions()));
        self::assertSame([], $container->getDefinition(InMemoryScoreStore::class)->getArguments());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryScoreStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemorySnapshotStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryAppearanceStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryLeagueStore::class)->getTags());
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryLeadStore::class)->getTags());
        self::assertSame([InMemoryAppearanceStore::class, true], [(string) $container->getAlias(AppearanceStore::class), $container->getAlias(AppearanceStore::class)->isPublic()]);
        self::assertEquals([
            'games-month' => ['measure' => 'count', 'verbs' => ['game.played'], 'context' => ['ranked' => true], 'period' => 'month', 'window' => 0, 'timezone' => null, 'seasons' => [], 'ties' => 'shared', 'tie_breaks' => [], 'per_object' => false, 'snapshot_size' => 10],
            'summer' => ['measure' => 'total', 'verbs' => ['quest.*'], 'context' => [], 'period' => 'season', 'window' => 0, 'timezone' => 'Europe/Paris', 'seasons' => [['code' => 'summer-2026', 'start' => '2026-06-21', 'end' => '2026-09-22']], 'ties' => 'first', 'tie_breaks' => [], 'per_object' => false, 'snapshot_size' => 100],
            'level' => ['measure' => 'latest', 'verbs' => [], 'context' => [], 'period' => 'ever', 'window' => 0, 'timezone' => null, 'seasons' => [], 'ties' => 'first', 'tie_breaks' => [], 'per_object' => false, 'snapshot_size' => 100],
        ], $container->getDefinition(Boards::class)->getArgument(0));
        self::assertSame('America/Toronto', $container->getDefinition(Boards::class)->getArgument(1));
        self::assertSame('ranks', $container->getDefinition(LeaderboardProjection::class)->getArgument(2));
        self::assertSame('ranks', $container->getDefinition(LeaderboardsExtension::class)->getArgument(1));
        self::assertSame('user', $container->getDefinition(LeaderboardsPrivacy::class)->getArgument(1));

        $other = $this->load(['connection' => 'gamification']);
        self::assertEquals([new Reference('doctrine.dbal.gamification_connection')], $other->getDefinition(DbalSnapshotStore::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'gamification']]], $other->getDefinition(LeaderboardsSchemaListener::class)->getTags());
        self::assertTrue($this->load(['store' => 'doctrine'])->hasDefinition(DbalScoreStore::class));
    }

    public function testTheEdgesOfTheConfiguration(): void
    {
        $boards = $this->load(['boards' => [
            'one' => ['measure' => 'best', 'verbs' => ['a.b'], 'context' => ['the-key' => 1, 'flag' => false], 'snapshot_size' => 1],
            'two' => ['measure' => 'highest', 'snapshot_size' => 1000, 'period' => 'day', 'ties' => 'first'],
        ]])->getDefinition(Boards::class)->getArgument(0);

        self::assertIsArray($boards);
        self::assertSame(['one', 'two'], array_keys($boards));
        self::assertIsArray($boards['one']);
        self::assertSame(['the_key' => 1, 'flag' => false], $boards['one']['context']);
        self::assertIsArray($boards['two']);
        self::assertSame(['day', 'first'], [$boards['two']['period'], $boards['two']['ties']]);
    }

    public function testALeagueAndTheSchedule(): void
    {
        $container = $this->load([
            'boards' => [
                'weekly' => ['measure' => 'total', 'verbs' => ['lesson.done'], 'period' => 'week', 'league' => ['tiers' => ['bronze', 'silver']]],
                'tuned' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['low', 'high'], 'division_size' => 2, 'promoted' => 0, 'relegated' => 0, 'formed_by' => 'volume']],
                'big' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['a'], 'division_size' => 100]],
            ],
            'schedule' => ['enabled' => true, 'frequency' => '15 minutes'],
        ]);
        $boards = $container->getDefinition(Boards::class)->getArgument(0);

        self::assertIsArray($boards);
        self::assertIsArray($boards['weekly']);
        self::assertSame(['tiers' => ['bronze', 'silver'], 'division_size' => 30, 'promoted' => 7, 'relegated' => 5, 'formed_by' => 'arrival'], $boards['weekly']['league']);
        self::assertIsArray($boards['tuned']);
        self::assertSame(['tiers' => ['low', 'high'], 'division_size' => 2, 'promoted' => 0, 'relegated' => 0, 'formed_by' => 'volume'], $boards['tuned']['league']);
        self::assertSame(['15 minutes'], $container->getDefinition(LeaderboardsSchedule::class)->getArguments());
        self::assertSame(['scheduler.schedule_provider' => [['name' => 'tilttek_leaderboards']]], $container->getDefinition(LeaderboardsSchedule::class)->getTags());
        self::assertEquals([new Reference(Leaderboards::class), new Reference(Leagues::class), new Reference(LeaderboardProjection::class), new Reference('clock')], $container->getDefinition(TakeDueHandler::class)->getArguments());
        self::assertSame(['messenger.message_handler' => [['handles' => TakeDue::class]]], $container->getDefinition(TakeDueHandler::class)->getTags());
        self::assertTrue($container->getDefinition(TakeDueHandler::class)->isPublic());
        self::assertSame(['1 hour'], $this->load(['schedule' => true])->getDefinition(LeaderboardsSchedule::class)->getArguments());
        self::assertFalse($this->load([])->hasDefinition(LeaderboardsSchedule::class));
        self::assertFalse($this->load([])->hasDefinition(TakeDueHandler::class));
    }

    public function testTheNewOptionsOfABoard(): void
    {
        $container = $this->load([
            'boards' => [
                'arcade' => ['measure' => 'best', 'verbs' => ['arcade.scored'], 'per_object' => true, 'tie_breaks' => [['measure' => 'count', 'order' => 'asc'], ['measure' => 'lowest', 'context' => 'seconds']]],
                'regular' => ['measure' => 'days', 'verbs' => ['lesson.done'], 'period' => 'window', 'window' => 7, 'title' => ['name' => 'regular', 'threshold' => 5]],
                'cup' => ['measure' => 'total', 'verbs' => ['quest.done'], 'chance' => ['dice' => 2]],
            ],
            'secret' => 'a secret',
            'kit_subdirectory' => 'ui',
        ]);
        $boards = $container->getDefinition(Boards::class)->getArgument(0);

        self::assertIsArray($boards);
        self::assertIsArray($boards['arcade']);
        self::assertSame([['measure' => 'count', 'order' => 'asc', 'context' => null], ['measure' => 'lowest', 'context' => 'seconds', 'order' => 'desc']], $boards['arcade']['tie_breaks']);
        self::assertTrue($boards['arcade']['per_object']);
        self::assertIsArray($boards['regular']);
        self::assertSame([7, ['name' => 'regular', 'threshold' => 5, 'leader' => false]], [$boards['regular']['window'], $boards['regular']['title']]);
        self::assertIsArray($boards['cup']);
        self::assertSame(['dice' => 2, 'faces' => 6], $boards['cup']['chance']);
        self::assertSame('a secret', $container->getDefinition(Leaderboards::class)->getArgument(7));
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());
    }

    public function testTheEdgesOfTheOptionsOfABoard(): void
    {
        $container = $this->load(['boards' => [
            'long' => ['measure' => 'count', 'verbs' => ['a'], 'period' => 'window', 'window' => 366],
            'none' => ['measure' => 'count', 'verbs' => ['a'], 'window' => 0, 'tie_breaks' => [['measure' => 'count', 'order' => 'desc']], 'title' => ['name' => 't', 'threshold' => 1]],
            'most' => ['measure' => 'count', 'verbs' => ['a'], 'chance' => ['dice' => 10, 'faces' => 100]],
            'least' => ['measure' => 'count', 'verbs' => ['a'], 'chance' => ['dice' => 1, 'faces' => 2]],
            'faces' => ['measure' => 'count', 'verbs' => ['a'], 'chance' => ['faces' => 20]],
            'arrival' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['a'], 'formed_by' => 'arrival']],
        ]]);
        $boards = $container->getDefinition(Boards::class)->getArgument(0);

        self::assertIsArray($boards);
        self::assertIsArray($boards['long']);
        self::assertSame(366, $boards['long']['window']);
        self::assertIsArray($boards['none']);
        self::assertEquals(['window' => 0, 'tie_breaks' => [['measure' => 'count', 'order' => 'desc', 'context' => null]], 'title' => ['name' => 't', 'threshold' => 1, 'leader' => false]], array_intersect_key($boards['none'], array_flip(['window', 'tie_breaks', 'title'])));
        self::assertIsArray($boards['most']);
        self::assertSame(['dice' => 10, 'faces' => 100], $boards['most']['chance']);
        self::assertIsArray($boards['least']);
        self::assertSame(['dice' => 1, 'faces' => 2], $boards['least']['chance']);
        self::assertIsArray($boards['faces']);
        self::assertEquals(['dice' => 1, 'faces' => 20], $boards['faces']['chance']);
        self::assertIsArray($boards['arrival']);
        self::assertIsArray($boards['arrival']['league']);
        self::assertSame('arrival', $boards['arrival']['league']['formed_by']);
    }

    public function testTheScoresInValkey(): void
    {
        $container = $this->load(['valkey' => ['dsn' => 'redis://cache:6379/2', 'prefix' => 'ranks']]);

        self::assertSame([RedisAdapter::class, 'createConnection'], $container->getDefinition(LeaderboardsServices::VALKEY_CLIENT)->getFactory());
        self::assertSame(['redis://cache:6379/2'], $container->getDefinition(LeaderboardsServices::VALKEY_CLIENT)->getArguments());
        self::assertEquals([new Reference(LeaderboardsServices::VALKEY_CLIENT)], $container->getDefinition(RawCommandConnection::class)->getArguments());
        self::assertEquals([new Reference(RawCommandConnection::class), new Reference(Boards::class), 'ranks'], $container->getDefinition(ValkeyScoreStore::class)->getArguments());
        self::assertSame([ValkeyScoreStore::class, true], [(string) $container->getAlias(ScoreStore::class), $container->getAlias(ScoreStore::class)->isPublic()]);
        self::assertSame(DbalSnapshotStore::class, (string) $container->getAlias(SnapshotStore::class));
        self::assertSame(['redis://localhost:6379'], $this->load(['valkey' => true])->getDefinition(LeaderboardsServices::VALKEY_CLIENT)->getArguments());
        self::assertFalse($this->load([])->hasDefinition(ValkeyScoreStore::class));
    }

    public function testTheScoresInValkeyThroughAClientOfTheApplication(): void
    {
        $container = $this->load(['valkey' => ['client' => 'app.valkey', 'dsn' => 'redis://ignored:6379']]);

        self::assertFalse($container->hasDefinition(LeaderboardsServices::VALKEY_CLIENT));
        self::assertSame('app.valkey', (string) $container->getAlias(LeaderboardsServices::VALKEY_CLIENT));
        self::assertEquals([new Reference(LeaderboardsServices::VALKEY_CLIENT)], $container->getDefinition(RawCommandConnection::class)->getArguments());
        self::assertSame(ValkeyScoreStore::class, (string) $container->getAlias(ScoreStore::class));
    }

    public function testTheClientOfTheApplicationIsNamed(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load(['valkey' => ['client' => '']]);
    }

    public function testTheBundleRegistersThePassOfTheKit(): void
    {
        $container = new ContainerBuilder();
        new TilttekLeaderboardsBundle()->build($container);

        self::assertContainsOnlyInstancesOf(KitSubdirectoryPass::class, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
    }

    public function testTheKitIsNamedAfterTheToolkit(): void
    {
        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui/kit');
        new KitSubdirectoryPass()->process($container);

        self::assertSame(['ui/kit'], $container->getDefinition(KitComponents::class)->getArguments());

        $without = $this->load([]);
        new KitSubdirectoryPass()->process($without);
        self::assertSame([''], $without->getDefinition(KitComponents::class)->getArguments());

        $own = $this->load(['kit_subdirectory' => 'mine']);
        $own->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($own);
        self::assertSame(['mine'], $own->getDefinition(KitComponents::class)->getArguments());

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(KitComponents::class));
    }

    public function testAWrongBoardFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidLeaderboard::because('The board "games" reads verbs when its measure comes from the journal, and only then.'));
        $this->load(['boards' => ['games' => ['measure' => 'count']]]);
    }

    public function testATimezoneThatIsNoneFailsWhenTheContainerIsBuilt(): void
    {
        $this->expectExceptionObject(InvalidLeaderboard::because('"Mars/Olympus" is not a timezone.'));
        $this->load(['timezone' => 'Mars/Olympus', 'boards' => ['level' => ['measure' => 'latest']]]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function wrongConfigurations(): iterable
    {
        yield 'store' => [['store' => 'redis']];
        yield 'empty connection' => [['connection' => '']];
        yield 'empty timezone' => [['timezone' => '']];
        yield 'empty projection' => [['projection' => '']];
        yield 'empty domain' => [['translation_domain' => '']];
        yield 'empty member kind' => [['privacy' => ['member_kind' => '']]];
        yield 'no measure' => [['boards' => ['b' => ['verbs' => ['a']]]]];
        yield 'measure' => [['boards' => ['b' => ['measure' => 'average']]]];
        yield 'period' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'decade']]]];
        yield 'ties' => [['boards' => ['b' => ['measure' => 'latest', 'ties' => 'last']]]];
        yield 'no snapshot' => [['boards' => ['b' => ['measure' => 'latest', 'snapshot_size' => 0]]]];
        yield 'snapshot too large' => [['boards' => ['b' => ['measure' => 'latest', 'snapshot_size' => 1001]]]];
        yield 'context' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'context' => ['x' => 1.5]]]]];
        yield 'season without code' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'season', 'seasons' => [['start' => '2026-06-21', 'end' => '2026-09-22']]]]]];
        yield 'season without start' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'season', 'seasons' => [['code' => 's', 'end' => '2026-09-22']]]]]];
        yield 'league without tiers' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'week', 'league' => ['division_size' => 30]]]]];
        yield 'league with no tier' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => []]]]]];
        yield 'division of one' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['a'], 'division_size' => 1]]]]];
        yield 'division too large' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['a'], 'division_size' => 101]]]]];
        yield 'fewer than none promoted' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['a'], 'promoted' => -1]]]]];
        yield 'fewer than none relegated' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['a'], 'relegated' => -1]]]]];
        yield 'empty frequency' => [['schedule' => ['frequency' => '']]];
        yield 'window too long' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'period' => 'window', 'window' => 367]]]];
        yield 'window before the first day' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'period' => 'window', 'window' => -1]]]];
        yield 'too many dice' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'chance' => ['dice' => 11]]]]];
        yield 'too many faces' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'chance' => ['faces' => 101]]]]];
        yield 'tie-break measure' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'tie_breaks' => [['measure' => 'median']]]]]];
        yield 'tie-break order' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'tie_breaks' => [['measure' => 'count', 'order' => 'up']]]]]];
        yield 'title without threshold' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'title' => ['name' => 't']]]]];
        yield 'title threshold of none' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'title' => ['name' => 't', 'threshold' => 0]]]]];
        yield 'no die' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'chance' => ['dice' => 0]]]]];
        yield 'one face' => [['boards' => ['b' => ['measure' => 'count', 'verbs' => ['a'], 'chance' => ['faces' => 1]]]]];
        yield 'formation' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'week', 'league' => ['tiers' => ['a'], 'formed_by' => 'chance']]]]];
        yield 'empty secret' => [['secret' => '']];
        yield 'empty Valkey' => [['valkey' => ['dsn' => '']]];
        yield 'empty prefix' => [['valkey' => ['prefix' => '']]];
        yield 'season without end' => [['boards' => ['b' => ['measure' => 'latest', 'period' => 'season', 'seasons' => [['code' => 's', 'start' => '2026-06-21']]]]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('wrongConfigurations')]
    public function testAWrongConfigurationIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekLeaderboardsBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
