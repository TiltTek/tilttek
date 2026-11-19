<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use LogicException;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\Reservation\Bookable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Scheduler\Schedule;
use Tilttek\EscapeRoom\Booking\SessionBooker;
use Tilttek\EscapeRoom\Contract\SeatLedger;
use Tilttek\EscapeRoom\Contract\SessionReservations;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\EscapeRoom\Reservation\BookableSessionReservations;
use Tilttek\EscapeRoom\Reservation\Contract\RoomBookables;
use Tilttek\EscapeRoom\Store\InMemorySessionReservations;
use Tilttek\EscapeRoomBundle\Application\ConfiguredRoomCatalog;
use Tilttek\EscapeRoomBundle\Application\Contract\EscapeRoomAccess;
use Tilttek\EscapeRoomBundle\Application\Contract\GameStore;
use Tilttek\EscapeRoomBundle\Application\Contract\PartyBookings;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;
use Tilttek\EscapeRoomBundle\Application\Contract\RoomCatalog;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenStore;
use Tilttek\EscapeRoomBundle\Application\Contract\TeamNames;
use Tilttek\EscapeRoomBundle\Application\EscapeRoomTopics;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Tilttek\EscapeRoomBundle\Application\NoEscapeRoomAccess;
use Tilttek\EscapeRoomBundle\Application\UnnamedTeams;
use Tilttek\EscapeRoomBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalGameStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalResultStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalScreenStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalSeatLedger;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomSchemaListener;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ConsentController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ConsoleController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\LeaderboardController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ScreenController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\Screens;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\Views;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryGameStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryResultStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryScreenStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemorySeatLedger;
use Tilttek\EscapeRoomBundle\Infrastructure\Mercure\ScreenSubscriptions;
use Tilttek\EscapeRoomBundle\Infrastructure\Privacy\EscapeRoomPrivacy;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\EscapeRoomSchedule;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\ExpireDue;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\ExpireDueHandler;
use Tilttek\EscapeRoomBundle\Infrastructure\Twig\EscapeRoomExtension;
use Tilttek\EscapeRoomBundle\Infrastructure\Twig\KitComponents;
use Tilttek\PuzzleBuilder\Contract\GuessLimiter;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Limiter\RateLimiterGuessLimiter;
use Tilttek\PuzzleBuilder\PuzzleEngine;
use Twig\Attribute\AsTwigFunction;

/**
 * @phpstan-type EscapeRoomConfig array{store: string, connection: string, rooms: array<string, array{title: string, duration: int, hint_penalty: int, skip_penalty: int, min_players: int, max_players: int, scenario: array<array-key, mixed>}>, room_catalog: ?string, access: ?string, team_names: ?string, reservations: ?string, room_bookables: ?string, guesses: array{limit: int, seconds: int}, guess_limiter: ?string, schedule: array{enabled: bool, frequency: string, batch: int}, mercure: array{enabled: bool, hub: ?string, hub_name: ?string, topic_prefix: string, poll: int}, layout: string, leaderboard_size: int, kit_subdirectory: ?string, celebration: array{enabled: bool, effect: string}, translation_domain: string, privacy: array{member_kind: string}}
 *
 * The bundle's services: the stores of the games, the results and the screens on Doctrine DBAL or in memory, the rooms
 * of the configuration, the puzzle engine and `EscapeRooms` telling the event dispatcher, the reservations of the
 * sessions — on `phil/reservation` when it is installed —, `GameDesk`, the screens and the console with their routes'
 * controllers, the parameters `MercureHubPass` wires the hub from, and — when their components are installed — the schema listener, the
 * schedule, the command, the Twig functions and the privacy module.
 */
final class EscapeRoomServices
{
    /** The name of the schedule that ends the games whose countdown reached zero. */
    public const string SCHEDULE = 'tilttek_escape_room';

    /** The rate limiter of the guesses, when the bundle builds it. */
    public const string GUESS_LIMITER_FACTORY = 'tilttek_escape_room.guess_limiter_factory';

    private const array STORES = [
        GameStore::class => [InMemoryGameStore::class, DbalGameStore::class],
        ResultStore::class => [InMemoryResultStore::class, DbalResultStore::class],
        ScreenStore::class => [InMemoryScreenStore::class, DbalScreenStore::class],
    ];

    private function __construct()
    {
    }

    /**
     * @param EscapeRoomConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
        $events = new Reference(EventDispatcherInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);

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
            $container->register(EscapeRoomSchemaListener::class, EscapeRoomSchemaListener::class)
                ->setArguments([$connection])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        if (null === $config['room_catalog']) {
            $container->register(ConfiguredRoomCatalog::class, ConfiguredRoomCatalog::class)->setArguments([$config['rooms']]);
            $container->setAlias(RoomCatalog::class, ConfiguredRoomCatalog::class)->setPublic(true);
        } else {
            $container->setAlias(RoomCatalog::class, $config['room_catalog'])->setPublic(true);
        }

        self::alias($container, EscapeRoomAccess::class, $config['access'], NoEscapeRoomAccess::class);
        self::alias($container, TeamNames::class, $config['team_names'], UnnamedTeams::class);
        self::guesses($container, $config);
        $container->register(PuzzleEngine::class.'.escape_room', PuzzleEngine::class)
            ->setArguments([new Reference(GuessLimiter::class.'.escape_room'), $events, new Reference('clock')]);
        $container->register(EscapeRooms::class, EscapeRooms::class)
            ->setArguments([new Reference(PuzzleEngine::class.'.escape_room'), $events, new Reference('clock')])
            ->setPublic(true);
        self::reservations($container, $config, $connection);
        $container->register(SessionBooker::class, SessionBooker::class)
            ->setArguments([new Reference(SessionReservations::class), $events, new Reference('clock')])
            ->setPublic(true);

        $container->register(EscapeRoomTopics::class, EscapeRoomTopics::class)->setArguments([$config['mercure']['topic_prefix']]);
        // The hub is wired by `MercureHubPass`, once every bundle has registered its services: the one named, or the
        // default hub of the application when Mercure is installed.
        $container->setParameter(MercureHubPass::HUB, $config['mercure']['enabled'] ? $config['mercure']['hub'] ?? MercureHubPass::DEFAULT_HUB : null);
        $container->setParameter(MercureHubPass::NAMED, $config['mercure']['enabled'] && null !== $config['mercure']['hub']);

        $container->register(GameDesk::class, GameDesk::class)
            ->setArguments([
                new Reference(EscapeRooms::class),
                new Reference(RoomCatalog::class),
                new Reference(GameStore::class),
                new Reference(ResultStore::class),
                new Reference(ScreenStore::class),
                new Reference(PuzzleEngine::class.'.escape_room'),
                new Reference('clock'),
                null,
            ])
            ->setPublic(true);

        self::screens($container, $config);
        self::integrations($container, $config);
    }

    private static function alias(ContainerBuilder $container, string $port, ?string $service, string $default): void
    {
        if (null === $service) {
            $container->register($default, $default);
            $service = $default;
        }

        $container->setAlias($port, $service)->setPublic(true);
    }

    /**
     * @param EscapeRoomConfig $config
     */
    private static function guesses(ContainerBuilder $container, array $config): void
    {
        if (null !== $config['guess_limiter']) {
            $container->setAlias(GuessLimiter::class.'.escape_room', $config['guess_limiter']);

            return;
        }

        if (class_exists(RateLimiterFactory::class)) {
            $container->register(self::GUESS_LIMITER_FACTORY, RateLimiterFactory::class)->setArguments([
                ['id' => 'tilttek_escape_room_guesses', 'policy' => 'sliding_window', 'limit' => $config['guesses']['limit'], 'interval' => \sprintf('%d seconds', $config['guesses']['seconds'])],
                $container->register(self::GUESS_LIMITER_FACTORY.'.storage', CacheStorage::class)->setArguments([new Reference('cache.app')]),
            ]);
            $container->register(GuessLimiter::class.'.escape_room', RateLimiterGuessLimiter::class)->setArguments([new Reference(self::GUESS_LIMITER_FACTORY)]);

            return;
        }

        $container->register(GuessLimiter::class.'.escape_room', InMemoryGuessLimiter::class)
            ->setArguments([$config['guesses']['limit'], $config['guesses']['seconds'], new Reference('clock')]);
    }

    /**
     * The reservations of the sessions: the application's service; otherwise those on `phil/reservation`, kept in
     * Doctrine DBAL or in memory, when it is installed; otherwise the memory store.
     *
     * @param EscapeRoomConfig $config
     */
    private static function reservations(ContainerBuilder $container, array $config, Reference $connection): void
    {
        if (null !== $config['reservations']) {
            $container->setAlias(SessionReservations::class, $config['reservations'])->setPublic(true);

            return;
        }

        if (!class_exists(Bookable::class)) {
            $container->register(InMemorySessionReservations::class, InMemorySessionReservations::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(SessionReservations::class, InMemorySessionReservations::class)->setPublic(true);

            return;
        }

        if ('memory' === $config['store']) {
            $container->register(InMemorySeatLedger::class, InMemorySeatLedger::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(SeatLedger::class, InMemorySeatLedger::class)->setPublic(true);
        } else {
            $container->register(DbalSeatLedger::class, DbalSeatLedger::class)->setArguments([$connection, new Reference('clock')]);
            $container->setAlias(SeatLedger::class, DbalSeatLedger::class)->setPublic(true);
        }

        $container->setAlias(PartyBookings::class, (string) $container->getAlias(SeatLedger::class));
        $container->register(BookableSessionReservations::class, BookableSessionReservations::class)->setArguments([
            new Reference(SeatLedger::class),
            null === $config['room_bookables'] ? null : new Reference($config['room_bookables']),
            new Reference('clock'),
        ]);
        $container->setAlias(SessionReservations::class, BookableSessionReservations::class)->setPublic(true);

        if (null !== $config['room_bookables']) {
            $container->setAlias(RoomBookables::class, $config['room_bookables']);
        }
    }

    /**
     * @param EscapeRoomConfig $config
     */
    private static function screens(ContainerBuilder $container, array $config): void
    {
        $container->register(ScreenSubscriptions::class, ScreenSubscriptions::class)->setArguments([
            new Reference(EscapeRoomTopics::class),
            null,
            null,
            new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            $config['mercure']['hub_name'],
        ]);
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);

        if (class_exists(AsTwigFunction::class)) {
            $container->register(EscapeRoomExtension::class, EscapeRoomExtension::class)
                ->setArguments([new Reference(KitComponents::class), $config['translation_domain']])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }

        $container->register(Screens::class, Screens::class)->setArguments([new Reference(GameDesk::class), new Reference(EscapeRoomAccess::class), new Reference('twig'), new Reference('security.csrf.token_manager'), $config['layout']]);
        $container->register(Views::class, Views::class)->setArguments([
            new Reference(GameDesk::class),
            new Reference('router'),
            new Reference(ScreenSubscriptions::class),
            $config['celebration']['enabled'] ? $config['celebration']['effect'] : null,
            $config['mercure']['poll'],
        ]);

        foreach ([
            ConsoleController::class => [new Reference(Screens::class), new Reference(GameDesk::class), new Reference(Views::class), new Reference('router')],
            ScreenController::class => [new Reference(Screens::class), new Reference(Views::class), new Reference('router')],
            ConsentController::class => [new Reference(Screens::class), new Reference(GameDesk::class), new Reference('router')],
            LeaderboardController::class => [new Reference(GameDesk::class), new Reference(RoomCatalog::class), new Reference(TeamNames::class), new Reference('twig'), $config['layout'], $config['leaderboard_size']],
        ] as $controller => $arguments) {
            $container->register($controller, $controller)->setArguments($arguments)->addTag('controller.service_arguments')->setPublic(true);
        }
    }

    /**
     * @param EscapeRoomConfig $config
     */
    private static function integrations(ContainerBuilder $container, array $config): void
    {
        if ($config['schedule']['enabled']) {
            if (!class_exists(Schedule::class)) {
                throw new LogicException('The schedule of the escape rooms needs Symfony Scheduler: composer require symfony/scheduler.');
            }

            $container->register(EscapeRoomSchedule::class, EscapeRoomSchedule::class)
                ->setArguments([$config['schedule']['frequency']])
                ->addTag('scheduler.schedule_provider', ['name' => self::SCHEDULE]);
            $container->register(ExpireDueHandler::class, ExpireDueHandler::class)
                ->setArguments([new Reference(GameDesk::class), $config['schedule']['batch']])
                ->addTag('messenger.message_handler', ['handles' => ExpireDue::class])
                ->setPublic(true);
        }

        if (class_exists(Command::class)) {
            $container->register(ExpireCommand::class, ExpireCommand::class)
                ->setArguments([new Reference(GameDesk::class)])
                ->addTag('console.command', ['command' => 'tilttek:escape-room:expire', 'description' => 'Ends the games whose countdown reached zero']);
        }

        // The privacy module, when `phil/privacy-bundle` is installed: a member's games leave with their account.
        if (interface_exists(PersonalDataEraser::class)) {
            $container->register(EscapeRoomPrivacy::class, EscapeRoomPrivacy::class)
                ->setArguments([
                    new Reference(GameStore::class),
                    new Reference(ResultStore::class),
                    new Reference(ScreenStore::class),
                    $config['privacy']['member_kind'],
                    $container->hasAlias(PartyBookings::class) ? new Reference(PartyBookings::class) : null,
                ])
                ->addTag(PhilPrivacyBundle::EXPORTER_TAG)
                ->addTag(PhilPrivacyBundle::ERASER_TAG)
                ->setPublic(true);
        }
    }
}
