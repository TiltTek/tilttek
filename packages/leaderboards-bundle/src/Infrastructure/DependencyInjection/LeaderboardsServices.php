<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Scheduler\Schedule;
use Tilttek\Leaderboards\Boards;
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
use Tilttek\LeaderboardsBundle\Infrastructure\Scheduler\LeaderboardsSchedule;
use Tilttek\LeaderboardsBundle\Infrastructure\Scheduler\TakeDue;
use Tilttek\LeaderboardsBundle\Infrastructure\Scheduler\TakeDueHandler;
use Tilttek\LeaderboardsBundle\Infrastructure\Twig\KitComponents;
use Tilttek\LeaderboardsBundle\Infrastructure\Twig\LeaderboardsExtension;
use Twig\Attribute\AsTwigFunction;

/**
 * @phpstan-type LeaderboardsConfig array{store: string, valkey: array{enabled: bool, dsn: string, client: ?string, prefix: string}, connection: string, timezone: string, boards: array<string, array{measure: string, verbs: list<string>, context: array<string, bool|int|string>, period: string, window: int, timezone: ?string, seasons: list<array{code: string, start: string, end: string}>, ties: string, tie_breaks: list<array{measure: string, order: string, context: ?string}>, per_object: bool, title?: array{name: string, threshold: int, leader: bool}, chance?: array{dice: int, faces: int}, snapshot_size: int, league?: array{tiers: list<string>, division_size: int, promoted: int, relegated: int, formed_by: string}}>, projection: string, schedule: array{enabled: bool, frequency: string}, secret: string, kit_subdirectory: ?string, celebration: array{enabled: bool, effect: string, celebrate_on: string}, translation_domain: string, privacy: array{member_kind: string}}
 *
 * The bundle's services: the five stores on Doctrine DBAL or in memory — the scores in Valkey when it is enabled, watched
 * for the lead when the first places are celebrated as scores change —, the boards from the configuration — checked
 * when the container is compiled —, `Leagues` and `Leaderboards` telling the event dispatcher, the projection tagged
 * for `tilttek/activity-bundle`, the schedule of the snapshots and settlements due when it is enabled, and — when
 * their components are installed — the schema listener, the commands and the Twig functions.
 */
final class LeaderboardsServices
{
    /**
     * The tag under which `tilttek/activity-bundle` finds its projections.
     */
    public const string PROJECTION_TAG = 'tilttek_activity.projection';

    /**
     * The name the projection's checkpoint is kept under, unless the configuration names another.
     */
    public const string DEFAULT_PROJECTION = LeaderboardProjection::NAME;

    /**
     * The service of `Leaderboards`, for the privacy module the bundle class registers.
     */
    public const string LEADERBOARDS = Leaderboards::class;

    private const array STORES = [
        ScoreStore::class => [InMemoryScoreStore::class, DbalScoreStore::class],
        SnapshotStore::class => [InMemorySnapshotStore::class, DbalSnapshotStore::class],
        AppearanceStore::class => [InMemoryAppearanceStore::class, DbalAppearanceStore::class],
        LeagueStore::class => [InMemoryLeagueStore::class, DbalLeagueStore::class],
        LeadStore::class => [InMemoryLeadStore::class, DbalLeadStore::class],
    ];

    /**
     * The client of the Valkey server: the service `valkey.client` names, or the one Symfony Cache connects from the DSN.
     */
    public const string VALKEY_CLIENT = 'tilttek_leaderboards.valkey_client';

    /**
     * The name of the schedule that takes the snapshots and settles the weeks due.
     */
    public const string SCHEDULE = 'tilttek_leaderboards';

    private function __construct()
    {
    }

    /**
     * @param LeaderboardsConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        Boards::fromArray($config['boards'], $config['timezone']);
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));

        foreach (self::STORES as $port => [$memory, $dbal]) {
            $store = 'memory' === $config['store'] ? $memory : $dbal;
            $definition = $container->register($store, $store);

            if ('memory' === $config['store']) {
                $definition->addTag('kernel.reset', ['method' => 'reset']);
            } else {
                $definition->setArguments([$connection]);
            }

            $container->setAlias($port, $store)->setPublic(true);
        }

        if ($config['valkey']['enabled']) {
            if (null !== $config['valkey']['client']) {
                $container->setAlias(self::VALKEY_CLIENT, $config['valkey']['client']);
            } else {
                if (!class_exists(RedisAdapter::class)) {
                    throw new LogicException('The scores in Valkey need Symfony Cache to connect from a DSN, or the service of a client: composer require symfony/cache, or set tilttek_leaderboards.valkey.client.');
                }

                $container->register(self::VALKEY_CLIENT, 'object')
                    ->setFactory([RedisAdapter::class, 'createConnection'])
                    ->setArguments([$config['valkey']['dsn']]);
            }

            $container->register(RawCommandConnection::class, RawCommandConnection::class)
                ->setArguments([new Reference(self::VALKEY_CLIENT)]);
            $container->register(ValkeyScoreStore::class, ValkeyScoreStore::class)
                ->setArguments([new Reference(RawCommandConnection::class), new Reference(Boards::class), $config['valkey']['prefix']]);
            $container->setAlias(ScoreStore::class, ValkeyScoreStore::class)->setPublic(true);
        }

        if ('memory' !== $config['store'] && class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(LeaderboardsSchemaListener::class, LeaderboardsSchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        $container->register(Boards::class, Boards::class)
            ->setFactory([Boards::class, 'fromArray'])
            ->setArguments([$config['boards'], $config['timezone']])
            ->setPublic(true);
        $celebrateOn = CelebrateOn::from($config['celebration']['celebrate_on']);
        $container->register(DispatchingObserver::class, DispatchingObserver::class)
            ->setArguments([new Reference(EventDispatcherInterface::class), new Reference(Boards::class), $celebrateOn, new Reference(AppearanceStore::class)]);

        // The first places told as scores change: the store of scores the library is given watches the lead.
        if (CelebrateOn::ScoreChange === $celebrateOn) {
            $container->register(LeadWatch::class, LeadWatch::class)
                ->setArguments([new Reference((string) $container->getAlias(ScoreStore::class)), new Reference(Boards::class), new Reference(LeadStore::class), new Reference(EventDispatcherInterface::class), new Reference('clock'), new Reference(AppearanceStore::class)]);
            $container->setAlias(ScoreStore::class, LeadWatch::class)->setPublic(true);
        }

        $container->register(Leagues::class, Leagues::class)
            ->setArguments([new Reference(Boards::class), new Reference(ScoreStore::class), new Reference(LeagueStore::class), new Reference('clock'), new Reference(DispatchingObserver::class)])
            ->setPublic(true);
        $container->register(Leaderboards::class, Leaderboards::class)
            ->setArguments([new Reference(Boards::class), new Reference(ScoreStore::class), new Reference(SnapshotStore::class), new Reference(AppearanceStore::class), new Reference('clock'), new Reference(DispatchingObserver::class), new Reference(Leagues::class), $config['secret']])
            ->setPublic(true);
        $container->register(LeaderboardProjection::class, LeaderboardProjection::class)
            ->setArguments([new Reference(Boards::class), new Reference(ScoreStore::class), $config['projection'], new Reference(Leagues::class), new Reference('clock')])
            ->addTag(self::PROJECTION_TAG)
            ->setPublic(true);

        if ($config['schedule']['enabled']) {
            if (!class_exists(Schedule::class)) {
                throw new LogicException('The schedule of the leaderboards needs Symfony Scheduler: composer require symfony/scheduler.');
            }

            $container->register(LeaderboardsSchedule::class, LeaderboardsSchedule::class)
                ->setArguments([$config['schedule']['frequency']])
                ->addTag('scheduler.schedule_provider', ['name' => self::SCHEDULE]);
            $container->register(TakeDueHandler::class, TakeDueHandler::class)
                ->setArguments([new Reference(Leaderboards::class), new Reference(Leagues::class), new Reference(LeaderboardProjection::class), new Reference('clock')])
                ->addTag('messenger.message_handler', ['handles' => TakeDue::class])
                ->setPublic(true);
        }

        if (class_exists(Command::class)) {
            foreach ([SnapshotCommand::class, ShowCommand::class] as $command) {
                $container->register($command, $command)
                    ->setArguments([new Reference(Leaderboards::class)])
                    ->addTag('console.command');
            }

            $container->register(SettleCommand::class, SettleCommand::class)
                ->setArguments([new Reference(Leagues::class)])
                ->addTag('console.command');
            $container->register(SlideCommand::class, SlideCommand::class)
                ->setArguments([new Reference(LeaderboardProjection::class), new Reference('clock')])
                ->addTag('console.command');
        }

        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);

        if (class_exists(AsTwigFunction::class)) {
            $container->register(LeaderboardsExtension::class, LeaderboardsExtension::class)
                ->setArguments([new Reference(Leaderboards::class), $config['translation_domain'], new Reference(Leagues::class), new Reference(KitComponents::class), new Reference('clock'), $config['celebration']['enabled'] ? $config['celebration']['effect'] : null, $celebrateOn, new Reference(LeadStore::class)])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }
    }
}
