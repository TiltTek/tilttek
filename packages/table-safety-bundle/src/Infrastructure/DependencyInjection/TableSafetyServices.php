<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\ContentWarnings\BaseCatalogue;
use Phil\ContentWarnings\Catalogue;
use Phil\PagedMedia\Pdf\Document;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\Authorization;
use Tilttek\TableSafety\Contract\FeedbackStore;
use Tilttek\TableSafety\Contract\ParticipationStore;
use Tilttek\TableSafety\Contract\SignalObserver;
use Tilttek\TableSafety\Contract\SignalStore;
use Tilttek\TableSafety\Contract\TableStore;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\SessionZero\Template;
use Tilttek\TableSafety\SessionZero\Templates;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Store\InMemoryFeedbackStore;
use Tilttek\TableSafety\Store\InMemoryParticipationStore;
use Tilttek\TableSafety\Store\InMemorySignalStore;
use Tilttek\TableSafety\Store\InMemoryTableStore;
use Tilttek\TableSafety\TableSafety;
use Tilttek\TableSafety\TableSettings;
use Tilttek\TableSafetyBundle\Application\Contract\TableAccess;
use Tilttek\TableSafetyBundle\Application\NoTableAccess;
use Tilttek\TableSafetyBundle\Application\SignalTopics;
use Tilttek\TableSafetyBundle\Infrastructure\Console\PurgeSignalsCommand;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\DbalFeedbackStore;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\DbalParticipationStore;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\DbalSignalStore;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\DbalTableStore;
use Tilttek\TableSafetyBundle\Infrastructure\Doctrine\TableSafetySchemaListener;
use Tilttek\TableSafetyBundle\Infrastructure\Form\SafetyToolsType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\SessionZeroType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\StarsAndWishesType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\TableSettingsType;
use Tilttek\TableSafetyBundle\Infrastructure\Form\ThemeAnswersType;
use Tilttek\TableSafetyBundle\Infrastructure\Http\AnswersController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\CardsController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\GameMasterController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\Screens;
use Tilttek\TableSafetyBundle\Infrastructure\Http\TableController;
use Tilttek\TableSafetyBundle\Infrastructure\Http\Views;
use Tilttek\TableSafetyBundle\Infrastructure\Mercure\MercureSignalObserver;
use Tilttek\TableSafetyBundle\Infrastructure\Mercure\SignalSubscriptions;
use Tilttek\TableSafetyBundle\Infrastructure\Print\SafetyCardsPdf;
use Tilttek\TableSafetyBundle\Infrastructure\Privacy\TableSafetyPrivacy;
use Tilttek\TableSafetyBundle\Infrastructure\Twig\KitComponents;
use Tilttek\TableSafetyBundle\Infrastructure\Twig\TableSafetyExtension;

/**
 * @phpstan-type TableSafetyConfig array{store: string, connection: string, table_access: ?string, layout: string, defaults: array{tools: list<string>, named_signals: bool, signals_seen_by: string, template: string}, mercure: array{hub: ?string, hub_name: ?string, topic_prefix: string}, retention: string, feedback_threshold: positive-int, kit_subdirectory: ?string, privacy: array{participant_kind: string}}
 *
 * The bundle's services: the four stores on Doctrine DBAL or in memory, `TableSafety` with the catalogue of
 * `phil/content-warnings-bundle` — or the base list without it — and the configured defaults, the application's
 * `TableAccess`, the signals pushed to Mercure when a hub is named, the forms, the screens and their controllers, the
 * Twig functions, the command that purges old signals, and — when their packages are installed — the schema listener
 * and the privacy module.
 */
final class TableSafetyServices
{
    private const array STORES = [
        TableStore::class => [InMemoryTableStore::class, DbalTableStore::class],
        ParticipationStore::class => [InMemoryParticipationStore::class, DbalParticipationStore::class],
        SignalStore::class => [InMemorySignalStore::class, DbalSignalStore::class],
        FeedbackStore::class => [InMemoryFeedbackStore::class, DbalFeedbackStore::class],
    ];

    private function __construct()
    {
    }

    /**
     * @param TableSafetyConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::stores($container, $config);
        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $hub = null === $config['mercure']['hub'] ? null : new Reference($config['mercure']['hub']);

        if (!$container->hasDefinition(Catalogue::class)) {
            $container->register(Catalogue::class, Catalogue::class)->setFactory([BaseCatalogue::class, 'load']);
        }

        $container->register(SignalTopics::class, SignalTopics::class)->setArguments([$config['mercure']['topic_prefix']]);

        if ($hub instanceof Reference) {
            $container->register(MercureSignalObserver::class, MercureSignalObserver::class)->setArguments([$hub, new Reference(SignalTopics::class), $logger]);
            $container->setAlias(SignalObserver::class, MercureSignalObserver::class);
        }

        $container->register(TableSettings::class.'.defaults', TableSettings::class)->setArguments([
            array_map(SafetyTool::from(...), $config['defaults']['tools']),
            $config['defaults']['named_signals'],
            Audience::from($config['defaults']['signals_seen_by']),
            [],
            new Definition(Template::class)->setFactory([Templates::class, 'get'])->setArguments([$config['defaults']['template']]),
        ]);

        $container->register(TableSafety::class, TableSafety::class)
            ->setArguments([
                new Reference(Catalogue::class),
                new Reference(TableStore::class),
                new Reference(ParticipationStore::class),
                new Reference(SignalStore::class),
                new Reference(FeedbackStore::class),
                $hub instanceof Reference ? new Reference(SignalObserver::class) : null,
                new Reference(TableSettings::class.'.defaults'),
                $config['feedback_threshold'],
            ])
            ->setPublic(true);

        if (null === $config['table_access']) {
            $container->register(NoTableAccess::class, NoTableAccess::class);
            $container->setAlias(TableAccess::class, NoTableAccess::class);
        } else {
            $container->setAlias(TableAccess::class, $config['table_access']);
        }

        self::screens($container, $config, $hub);
        self::integrations($container, $config);
    }

    /**
     * @param TableSafetyConfig $config
     */
    private static function stores(ContainerBuilder $container, array $config): void
    {
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));

        foreach (self::STORES as $contract => [$memory, $dbal]) {
            $class = 'memory' === $config['store'] ? $memory : $dbal;
            $definition = $container->register($class, $class);

            if ($dbal === $class) {
                $definition->setArguments([$connection]);
            }

            $container->setAlias($contract, $class)->setPublic(true);
        }

        if ('doctrine' === $config['store'] && class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(TableSafetySchemaListener::class, TableSafetySchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }
    }

    /**
     * @param TableSafetyConfig $config
     */
    private static function screens(ContainerBuilder $container, array $config, ?Reference $hub): void
    {
        $container->register(SignalSubscriptions::class, SignalSubscriptions::class)->setArguments([
            new Reference(SignalTopics::class),
            $hub,
            $hub instanceof Reference && class_exists(Authorization::class) ? new Reference(Authorization::class, ContainerInterface::NULL_ON_INVALID_REFERENCE) : null,
            new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            $config['mercure']['hub_name'],
        ]);

        foreach ([ThemeAnswersType::class, SessionZeroType::class, StarsAndWishesType::class, SafetyToolsType::class, TableSettingsType::class] as $type) {
            $container->register($type, $type)->addTag('form.type');
        }

        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(TableSafetyExtension::class, TableSafetyExtension::class)
            ->setArguments([new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        $container->register(Screens::class, Screens::class)->setArguments([new Reference(TableAccess::class), new Reference('twig'), new Reference('security.csrf.token_manager'), $config['layout']]);
        $container->register(Views::class, Views::class)->setArguments([new Reference(TableSafety::class), new Reference('router')]);

        $container->register(TableController::class, TableController::class)
            ->setArguments([new Reference(Screens::class), new Reference(TableSafety::class), new Reference('clock'), new Reference('router'), new Reference(Views::class), new Reference(SignalSubscriptions::class)])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(AnswersController::class, AnswersController::class)
            ->setArguments([new Reference(Screens::class), new Reference(TableSafety::class), new Reference('form.factory'), new Reference('clock'), new Reference('router')])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        if (class_exists(Document::class)) {
            $container->register(SafetyCardsPdf::class, SafetyCardsPdf::class);
        }

        $container->register(CardsController::class, CardsController::class)
            ->setArguments([
                new Reference(Screens::class),
                new Reference(Views::class),
                new Reference('clock'),
                new Reference(SafetyCardsPdf::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(GameMasterController::class, GameMasterController::class)
            ->setArguments([new Reference(Screens::class), new Reference(TableSafety::class), new Reference('form.factory'), new Reference('router'), new Reference(Views::class), new Reference(SignalSubscriptions::class)])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
    }

    /**
     * @param TableSafetyConfig $config
     */
    private static function integrations(ContainerBuilder $container, array $config): void
    {
        if (class_exists(Command::class)) {
            $container->register(PurgeSignalsCommand::class, PurgeSignalsCommand::class)
                ->setArguments([new Reference(SignalStore::class), new Reference('clock'), $config['retention']])
                ->addTag('console.command', ['command' => 'tilttek:table-safety:purge-signals', 'description' => 'Deletes the signals older than the retention']);
        }

        // The privacy module, when `phil/privacy-bundle` is installed: a participant's answers leave with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(TableSafetyPrivacy::class, TableSafetyPrivacy::class)
                ->setArguments([new Reference(TableSafety::class), $config['privacy']['participant_kind']])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG)
                ->setPublic(true);
        }
    }
}
