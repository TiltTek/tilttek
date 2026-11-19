<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\AdventCalendar\AdventCalendar;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendarBundle\Application\Calendars;
use Tilttek\AdventCalendarBundle\Application\Celebrations;
use Tilttek\AdventCalendarBundle\Application\ConfiguredCalendars;
use Tilttek\AdventCalendarBundle\Application\Contract\CalendarCatalogue;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberOpenings;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberResolver;
use Tilttek\AdventCalendarBundle\Application\Members;
use Tilttek\AdventCalendarBundle\Application\NoMember;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\CheckCalendarsCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\ListCalendarsCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\Console\ShowCalendarCommand;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\DbalOpeningStore;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\DoctrineCalendarCatalogue;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\OpeningsSchemaListener;
use Tilttek\AdventCalendarBundle\Infrastructure\EventListener\CelebrationListener;
use Tilttek\AdventCalendarBundle\Infrastructure\Form\CalendarType;
use Tilttek\AdventCalendarBundle\Infrastructure\Form\SurpriseType;
use Tilttek\AdventCalendarBundle\Infrastructure\Http\CalendarController;
use Tilttek\AdventCalendarBundle\Infrastructure\Memory\InMemoryOpenings;
use Tilttek\AdventCalendarBundle\Infrastructure\Privacy\AdventCalendarPrivacy;
use Tilttek\AdventCalendarBundle\Infrastructure\Twig\AdventCalendarExtension;
use Tilttek\AdventCalendarBundle\Infrastructure\Twig\KitComponents;

/**
 * @phpstan-import-type CalendarConfig from ConfiguredCalendars
 *
 * @phpstan-type AdventCalendarConfig array{store: string, connection: string, entity_manager: string, member_resolver: ?string, layout: string, kit_subdirectory: ?string, calendars: array<string, CalendarConfig>, celebration: array{enabled: bool, effect: string}, privacy: array{member_kind: string}}
 *
 * The bundle's services: the openings on Doctrine DBAL or in memory, the calendars of the configuration and — with the
 * ORM — of the database, `AdventCalendar` with the event dispatcher of the application, the member resolver, the hook
 * of the celebration, the forms, the screen and its controller, the Twig function, and — when their packages are
 * installed — the commands, the schema listener and the privacy module.
 */
final class AdventCalendarServices
{
    private function __construct()
    {
    }

    /**
     * @param AdventCalendarConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        self::stores($container, $config);

        $container->register(AdventCalendar::class, AdventCalendar::class)
            ->setArguments([new Reference(OpeningStore::class), new Reference('clock'), new Reference('event_dispatcher')])
            ->setPublic(true);

        if (null === $config['member_resolver']) {
            $container->register(NoMember::class, NoMember::class);
            $container->setAlias(MemberResolver::class, NoMember::class);
        } else {
            $container->setAlias(MemberResolver::class, $config['member_resolver']);
        }

        $container->register(Members::class, Members::class)->setArguments([new Reference(MemberResolver::class)]);
        $container->register(Celebrations::class, Celebrations::class)->addTag('kernel.reset', ['method' => 'reset']);
        $container->register(CelebrationListener::class, CelebrationListener::class)
            ->setArguments([new Reference(Celebrations::class)])
            ->addTag('kernel.event_listener', ['event' => BoxOpened::class]);

        foreach ([SurpriseType::class, CalendarType::class] as $type) {
            $container->register($type, $type)->addTag('form.type');
        }

        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(AdventCalendarExtension::class, AdventCalendarExtension::class)
            ->setArguments([new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');

        $container->register(CalendarController::class, CalendarController::class)
            ->setArguments([
                new Reference(CalendarCatalogue::class),
                new Reference(AdventCalendar::class),
                new Reference(Members::class),
                new Reference(Celebrations::class),
                new Reference('clock'),
                new Reference('twig'),
                new Reference('security.csrf.token_manager'),
                new Reference('router'),
                $config['layout'],
                $config['celebration']['enabled'],
                $config['celebration']['effect'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);

        // The commands, when the console is installed.
        if (class_exists(Command::class)) {
            $container->register(ListCalendarsCommand::class, ListCalendarsCommand::class)
                ->setArguments([new Reference(CalendarCatalogue::class)])
                ->addTag('console.command', ['command' => ListCalendarsCommand::NAME, 'description' => ListCalendarsCommand::DESCRIPTION]);
            $container->register(ShowCalendarCommand::class, ShowCalendarCommand::class)
                ->setArguments([new Reference(CalendarCatalogue::class), new Reference('clock')])
                ->addTag('console.command', ['command' => ShowCalendarCommand::NAME, 'description' => ShowCalendarCommand::DESCRIPTION]);
        }

        // The privacy module, when `phil/privacy-bundle` is installed: a member's openings leave with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(AdventCalendarPrivacy::class, AdventCalendarPrivacy::class)
                ->setArguments([new Reference(AdventCalendar::class), new Reference(MemberOpenings::class), $config['privacy']['member_kind']])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG)
                ->setPublic(true);
        }
    }

    /**
     * @param AdventCalendarConfig $config
     */
    private static function stores(ContainerBuilder $container, array $config): void
    {
        $container->register(ConfiguredCalendars::class, ConfiguredCalendars::class)->setArguments([$config['calendars']]);
        $catalogues = [new Reference(ConfiguredCalendars::class)];

        if ('memory' === $config['store']) {
            $container->register(InMemoryOpenings::class, InMemoryOpenings::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(OpeningStore::class, InMemoryOpenings::class)->setPublic(true);
            $container->setAlias(MemberOpenings::class, InMemoryOpenings::class);
        } else {
            $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
            $container->register(DbalOpeningStore::class, DbalOpeningStore::class)->setArguments([$connection]);
            $container->setAlias(OpeningStore::class, DbalOpeningStore::class)->setPublic(true);
            $container->setAlias(MemberOpenings::class, DbalOpeningStore::class);

            if (interface_exists(EntityManagerInterface::class)) {
                $container->register(DoctrineCalendarCatalogue::class, DoctrineCalendarCatalogue::class)->setArguments([
                    new Reference(\sprintf('doctrine.orm.%s_entity_manager', $config['entity_manager'])),
                    new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);
                $catalogues[] = new Reference(DoctrineCalendarCatalogue::class);

                if (class_exists(Command::class)) {
                    $container->register(CheckCalendarsCommand::class, CheckCalendarsCommand::class)
                        ->setArguments([new Reference(\sprintf('doctrine.orm.%s_entity_manager', $config['entity_manager']))])
                        ->addTag('console.command', ['command' => CheckCalendarsCommand::NAME, 'description' => CheckCalendarsCommand::DESCRIPTION]);
                }
            }

            if (class_exists(GenerateSchemaEventArgs::class)) {
                $container->register(OpeningsSchemaListener::class, OpeningsSchemaListener::class)
                    ->setArguments([$connection])
                    ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
            }
        }

        $container->register(Calendars::class, Calendars::class)->setArguments([new IteratorArgument($catalogues)]);
        $container->setAlias(CalendarCatalogue::class, Calendars::class)->setPublic(true);
    }
}
