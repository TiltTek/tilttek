<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\DependencyInjection;

use DateInterval;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use LogicException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;
use Tilttek\Activity\Journal;
use Tilttek\Activity\JournalObserver;
use Tilttek\Activity\Log\ActivityLog;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Projection\Checkpoints;
use Tilttek\Activity\Projection\InMemoryCheckpoints;
use Tilttek\Activity\Projection\LiveProjections;
use Tilttek\Activity\Projection\Projection;
use Tilttek\Activity\Projection\Projections;
use Tilttek\Activity\Projection\Projector;
use Tilttek\Activity\Verbs;
use Tilttek\ActivityBundle\Infrastructure\Console\CatchUpCommand;
use Tilttek\ActivityBundle\Infrastructure\Console\RebuildCommand;
use Tilttek\ActivityBundle\Infrastructure\Console\StatusCommand;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\ActivitySchemaListener;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\DbalActivityLog;
use Tilttek\ActivityBundle\Infrastructure\Doctrine\DbalCheckpoints;
use Tilttek\ActivityBundle\Infrastructure\Messenger\CatchUpProjection;
use Tilttek\ActivityBundle\Infrastructure\Messenger\CatchUpProjectionHandler;
use Tilttek\ActivityBundle\Infrastructure\Messenger\MessengerProjections;

/**
 * The bundle's services: the journal and the checkpoints on Doctrine DBAL or in memory, the declared verbs, the
 * projections and observers found by their tags, the projector, the `Journal`, the observer that keeps the projections
 * up to date — in the request or through Messenger —, and — when their components are installed — the schema listener
 * and the commands.
 */
final class ActivityServices
{
    public const string PROJECTION_TAG = 'tilttek_activity.projection';

    public const string OBSERVER_TAG = 'tilttek_activity.observer';

    /**
     * Each new entry catches up the projections that read it, in the same request.
     */
    public const string LIVE = 'live';

    /**
     * Each new entry sends the projections that read it a `CatchUpProjection` message.
     */
    public const string MESSENGER = 'messenger';

    /**
     * Only `tilttek:activity:catch-up` catches the projections up.
     */
    public const string MANUAL = 'manual';

    /**
     * The service of the `Journal`, for the privacy module the bundle class registers.
     */
    public const string JOURNAL = Journal::class;

    private function __construct()
    {
    }

    /**
     * @param array{store: string, connection: string, verbs: list<string>, batch_size: int, projections: string, projection_bus: string, dates: array{past: ?string, ahead: string}, privacy: array{member_kind: string}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        Verbs::of($config['verbs']);

        if ('memory' === $config['store']) {
            $container->register(InMemoryActivityLog::class, InMemoryActivityLog::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(ActivityLog::class, InMemoryActivityLog::class)->setPublic(true);
            $container->register(InMemoryCheckpoints::class, InMemoryCheckpoints::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(Checkpoints::class, InMemoryCheckpoints::class)->setPublic(true);
        } else {
            $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
            $container->register(DbalActivityLog::class, DbalActivityLog::class)->setArguments([$connection]);
            $container->setAlias(ActivityLog::class, DbalActivityLog::class)->setPublic(true);
            $container->register(DbalCheckpoints::class, DbalCheckpoints::class)->setArguments([$connection, new Reference('clock')]);
            $container->setAlias(Checkpoints::class, DbalCheckpoints::class)->setPublic(true);

            if (class_exists(GenerateSchemaEventArgs::class)) {
                $container->register(ActivitySchemaListener::class, ActivitySchemaListener::class)
                    ->setArguments([$connection])
                    ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
            }
        }

        $container->registerForAutoconfiguration(Projection::class)->addTag(self::PROJECTION_TAG);
        $container->registerForAutoconfiguration(JournalObserver::class)->addTag(self::OBSERVER_TAG);

        $container->register(Verbs::class, Verbs::class)->setFactory([Verbs::class, 'of'])->setArguments([$config['verbs']]);
        $container->register(Projections::class, Projections::class)->setArguments([new TaggedIteratorArgument(self::PROJECTION_TAG)])->setPublic(true);
        $container->register(Projector::class, Projector::class)
            ->setArguments([new Reference(ActivityLog::class), new Reference(Checkpoints::class), $config['batch_size']])
            ->setPublic(true);
        $container->register(Journal::class, Journal::class)
            ->setArguments([new Reference(ActivityLog::class), new Reference('clock'), new Reference(Verbs::class), new TaggedIteratorArgument(self::OBSERVER_TAG),
                null === $config['dates']['past'] ? null : new Definition(DateInterval::class, [$config['dates']['past']]),
                new Definition(DateInterval::class, [$config['dates']['ahead']]),
            ])
            ->setPublic(true);

        if (self::LIVE === $config['projections']) {
            $container->register(LiveProjections::class, LiveProjections::class)
                ->setArguments([new Reference(Projector::class), new Reference(Projections::class)])
                ->addTag(self::OBSERVER_TAG);
        }

        if (self::MESSENGER === $config['projections']) {
            if (!interface_exists(MessageBusInterface::class)) {
                throw new LogicException('Projections that follow the journal through Messenger need it: composer require symfony/messenger.');
            }

            $container->register(MessengerProjections::class, MessengerProjections::class)
                ->setArguments([new Reference($config['projection_bus']), new Reference(Projections::class)])
                ->addTag(self::OBSERVER_TAG);
            $container->register(CatchUpProjectionHandler::class, CatchUpProjectionHandler::class)
                ->setArguments([new Reference(Projector::class), new Reference(Projections::class), new Reference(Checkpoints::class)])
                ->addTag('messenger.message_handler', ['handles' => CatchUpProjection::class, 'bus' => $config['projection_bus']])
                ->setPublic(true);
        }

        if (class_exists(Command::class)) {
            foreach ([CatchUpCommand::class, RebuildCommand::class, StatusCommand::class] as $command) {
                $container->register($command, $command)
                    ->setArguments([new Reference(Projector::class), new Reference(Projections::class)])
                    ->addTag('console.command');
            }
        }
    }
}
