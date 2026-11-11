<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\DependencyInjection;

use DateTimeZone;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Progression\Progression;
use Tilttek\Progression\ProgressionProjection;
use Tilttek\Progression\Store\ExperienceStore;
use Tilttek\Progression\Store\InMemoryExperienceStore;
use Tilttek\Progression\Store\InMemoryMilestoneStore;
use Tilttek\Progression\Store\InMemoryStreakStore;
use Tilttek\Progression\Store\MilestoneStore;
use Tilttek\Progression\Store\StreakStore;
use Tilttek\Progression\Streak\ContextTimezone;
use Tilttek\Progression\Streak\FixedTimezone;
use Tilttek\Progression\Streak\StreakKeeper;
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Streak\TimezoneResolver;
use Tilttek\Progression\Tracks;
use Tilttek\ProgressionBundle\Infrastructure\Console\ShowCommand;
use Tilttek\ProgressionBundle\Infrastructure\Console\SweepCommand;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\DbalExperienceStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\DbalMilestoneStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\DbalStreakStore;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\ProgressionSchemaListener;
use Tilttek\ProgressionBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\ProgressionBundle\Infrastructure\Twig\ProgressionExtension;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the three stores on Doctrine DBAL or in memory, the tracks and the streaks from the
 * configuration — checked when the container is compiled —, the timezone of a member's days, `Progression`, the
 * projection tagged for `tilttek/activity-bundle` and telling the event dispatcher, and — when their components are
 * installed — the schema listener, the commands and the Twig functions.
 */
final class ProgressionServices
{
    /**
     * The tag under which `tilttek/activity-bundle` finds its projections.
     */
    public const string PROJECTION_TAG = 'tilttek_activity.projection';

    /**
     * The name the projection's checkpoint is kept under, unless the configuration names another.
     */
    public const string DEFAULT_PROJECTION = ProgressionProjection::NAME;

    /**
     * The service of `Progression`, for the privacy module the bundle class registers.
     */
    public const string PROGRESSION = Progression::class;

    private const array STORES = [
        ExperienceStore::class => [InMemoryExperienceStore::class, DbalExperienceStore::class],
        MilestoneStore::class => [InMemoryMilestoneStore::class, DbalMilestoneStore::class],
        StreakStore::class => [InMemoryStreakStore::class, DbalStreakStore::class],
    ];

    private function __construct()
    {
    }

    /**
     * @param array{store: string, connection: string, timezone: string, tracks: array<string, array{curve: array{type: string, step?: int, base?: int, factor?: float|int, levels?: int, thresholds?: list<int>}, subject: string, seasons?: array{length?: ?string, named?: list<array{code: string, start: string, end: string}>}, rules: array<string, array{verbs: list<string>, experience: int, per_quantity: bool, cap?: array{amount: int, period: string}, context: array<string, bool|int|string>}>, milestones: array<string, array{experience?: int, level?: int}>}>, streaks: array<string, array{verbs: list<string>, unit: string, grace?: array{allowed: int, window: int}, context: array<string, bool|int|string>}>, timezone_context: ?string, projection: string, translation_domain: string, privacy: array{member_kind: string}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        Tracks::fromArray($config['tracks'], $config['timezone']);
        StreakRules::fromArray($config['streaks'], $config['timezone']);
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

        if ('memory' !== $config['store'] && class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(ProgressionSchemaListener::class, ProgressionSchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        $container->register(Tracks::class, Tracks::class)
            ->setFactory([Tracks::class, 'fromArray'])
            ->setArguments([$config['tracks'], $config['timezone']])
            ->setPublic(true);
        $container->register(StreakRules::class, StreakRules::class)
            ->setFactory([StreakRules::class, 'fromArray'])
            ->setArguments([$config['streaks'], $config['timezone']])
            ->setPublic(true);
        $container->register('tilttek_progression.timezone', DateTimeZone::class)->setArguments([$config['timezone']]);

        if (null === $config['timezone_context']) {
            $container->register(TimezoneResolver::class, FixedTimezone::class)->setArguments([new Reference('tilttek_progression.timezone')]);
        } else {
            $container->register(TimezoneResolver::class, ContextTimezone::class)->setArguments([$config['timezone_context'], new Reference('tilttek_progression.timezone')]);
        }

        $container->register(DispatchingObserver::class, DispatchingObserver::class)
            ->setArguments([new Reference(EventDispatcherInterface::class)]);
        $container->register(StreakKeeper::class, StreakKeeper::class)
            ->setArguments([new Reference(StreakRules::class), new Reference(StreakStore::class), new Reference(DispatchingObserver::class)]);
        $container->register(Progression::class, Progression::class)
            ->setArguments([new Reference(Tracks::class), new Reference(StreakKeeper::class), new Reference(ExperienceStore::class), new Reference(MilestoneStore::class), new Reference(StreakStore::class), new Reference('clock')])
            ->setPublic(true);
        $container->register(ProgressionProjection::class, ProgressionProjection::class)
            ->setArguments([new Reference(Tracks::class), new Reference(StreakKeeper::class), new Reference(ExperienceStore::class), new Reference(MilestoneStore::class), new Reference('clock'), new Reference(TimezoneResolver::class), new Reference(DispatchingObserver::class), $config['projection']])
            ->addTag(self::PROJECTION_TAG)
            ->addTag('kernel.reset', ['method' => 'clearReplay'])
            ->setPublic(true);

        if (class_exists(Command::class)) {
            foreach ([SweepCommand::class, ShowCommand::class] as $command) {
                $container->register($command, $command)
                    ->setArguments([new Reference(Progression::class)])
                    ->addTag('console.command');
            }
        }

        if (class_exists(AsTwigFunction::class)) {
            $container->register(ProgressionExtension::class, ProgressionExtension::class)
                ->setArguments([new Reference(Progression::class), $config['translation_domain']])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }
    }
}
