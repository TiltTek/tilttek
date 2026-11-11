<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\Almanac\Almanac;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\Quests\Catalog;
use Tilttek\Quests\Duel\Duels;
use Tilttek\Quests\Evaluator;
use Tilttek\Quests\InMemoryCatalog;
use Tilttek\Quests\QuestProjection;
use Tilttek\Quests\QuestReader;
use Tilttek\Quests\Quests;
use Tilttek\Quests\Store\CompletionStore;
use Tilttek\Quests\Store\ContributionStore;
use Tilttek\Quests\Store\DuelStore;
use Tilttek\Quests\Store\EnrolmentStore;
use Tilttek\Quests\Store\InMemoryCompletionStore;
use Tilttek\Quests\Store\InMemoryContributionStore;
use Tilttek\Quests\Store\InMemoryDuelStore;
use Tilttek\Quests\Store\InMemoryEnrolmentStore;
use Tilttek\Quests\WritableCatalog;
use Tilttek\QuestsBundle\Infrastructure\Almanac\AlmanacScheduleReader;
use Tilttek\QuestsBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\QuestsBundle\Infrastructure\Console\ListCommand;
use Tilttek\QuestsBundle\Infrastructure\Console\ShowCommand;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalCatalog;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalCompletionStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalContributionStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalDuelStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\DbalEnrolmentStore;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\QuestsSchemaListener;
use Tilttek\QuestsBundle\Infrastructure\Event\DispatchingObserver;
use Tilttek\QuestsBundle\Infrastructure\Twig\QuestsExtension;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the four stores on Doctrine DBAL or in memory, the reader of the quests — with the dates
 * `phil/almanac` computes when it is installed —, the catalog from the configuration — checked when the container is
 * compiled — or from the database, `Quests` and `Duels`, the projection tagged for `tilttek/activity-bundle` and
 * telling the event dispatcher, and — when their components are installed — the schema listener, the commands and
 * the Twig functions.
 */
final class QuestsServices
{
    /**
     * The tag under which `tilttek/activity-bundle` finds its projections.
     */
    public const string PROJECTION_TAG = 'tilttek_activity.projection';

    /**
     * The name the projection's checkpoint is kept under, unless the configuration names another.
     */
    public const string DEFAULT_PROJECTION = QuestProjection::NAME;

    /**
     * The service of `Quests`, for the privacy module the bundle class registers.
     */
    public const string QUESTS = Quests::class;

    private const array STORES = [
        ContributionStore::class => [InMemoryContributionStore::class, DbalContributionStore::class],
        EnrolmentStore::class => [InMemoryEnrolmentStore::class, DbalEnrolmentStore::class],
        CompletionStore::class => [InMemoryCompletionStore::class, DbalCompletionStore::class],
        DuelStore::class => [InMemoryDuelStore::class, DbalDuelStore::class],
    ];

    private function __construct()
    {
    }

    /**
     * @param array{store: string, connection: string, timezone: string, catalog: string, quests: array<string, array<array-key, mixed>>, duels: array{offer_lifetime: string}, projection: string, translation_domain: string, privacy: array{member_kind: string}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $almanac = class_exists(Almanac::class);
        InMemoryCatalog::fromArray($config['quests'], new QuestReader($config['timezone'], $almanac ? [new AlmanacScheduleReader()] : []));
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
            $container->register(QuestsSchemaListener::class, QuestsSchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        $container->register(QuestReader::class, QuestReader::class)
            ->setArguments([$config['timezone'], $almanac ? [new Reference(AlmanacScheduleReader::class)] : []]);

        if ($almanac) {
            $container->register(AlmanacScheduleReader::class, AlmanacScheduleReader::class);
        }

        if ('doctrine' === $config['catalog']) {
            $container->register(DbalCatalog::class, DbalCatalog::class)->setArguments([$connection, new Reference(QuestReader::class)]);
            $container->setAlias(Catalog::class, DbalCatalog::class)->setPublic(true);
            $container->setAlias(WritableCatalog::class, DbalCatalog::class)->setPublic(true);
        } else {
            $container->register(Catalog::class, InMemoryCatalog::class)
                ->setFactory([InMemoryCatalog::class, 'fromArray'])
                ->setArguments([$config['quests'], new Reference(QuestReader::class)])
                ->setPublic(true);
        }

        $container->register(DispatchingObserver::class, DispatchingObserver::class)
            ->setArguments([new Reference(EventDispatcherInterface::class)]);
        $container->register(Evaluator::class, Evaluator::class)
            ->setArguments([new Reference(Catalog::class), new Reference(ContributionStore::class), new Reference(EnrolmentStore::class), new Reference(CompletionStore::class), new Reference(DuelStore::class)]);
        $container->register(Quests::class, Quests::class)
            ->setArguments([new Reference(Evaluator::class), new Reference('clock')])
            ->setPublic(true);
        $container->register(Duels::class, Duels::class)
            ->setArguments([new Reference(Evaluator::class), new Reference('clock'), new Reference(DispatchingObserver::class), $config['duels']['offer_lifetime']])
            ->setPublic(true);
        $container->register(QuestProjection::class, QuestProjection::class)
            ->setArguments([new Reference(Evaluator::class), new Reference(DispatchingObserver::class), $config['projection']])
            ->addTag(self::PROJECTION_TAG)
            ->addTag('kernel.reset', ['method' => 'clearReplay'])
            ->setPublic(true);

        if (class_exists(Command::class)) {
            $container->register(ListCommand::class, ListCommand::class)->setArguments([new Reference(Catalog::class)])->addTag('console.command');
            $container->register(ShowCommand::class, ShowCommand::class)->setArguments([new Reference(Quests::class)])->addTag('console.command');
            $container->register(ExpireCommand::class, ExpireCommand::class)->setArguments([new Reference(Duels::class)])->addTag('console.command');
        }

        if (class_exists(AsTwigFunction::class)) {
            $container->register(QuestsExtension::class, QuestsExtension::class)
                ->setArguments([new Reference(Quests::class), new Reference(Duels::class), $config['translation_domain']])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }
    }
}
