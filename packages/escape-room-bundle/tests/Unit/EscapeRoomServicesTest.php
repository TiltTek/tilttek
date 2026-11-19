<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit;

use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Tilttek\EscapeRoom\Booking\SessionBooker;
use Tilttek\EscapeRoom\Contract\SeatLedger;
use Tilttek\EscapeRoom\Contract\SessionReservations;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\EscapeRoom\Reservation\BookableSessionReservations;
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
use Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection\EscapeRoomServices;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalGameStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalSeatLedger;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomSchemaListener;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ConsentController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ConsoleController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\LeaderboardController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\ScreenController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\Screens;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\Views;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemorySeatLedger;
use Tilttek\EscapeRoomBundle\Infrastructure\Mercure\ScreenSubscriptions;
use Tilttek\EscapeRoomBundle\Infrastructure\Privacy\EscapeRoomPrivacy;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\ExpireDueHandler;
use Tilttek\EscapeRoomBundle\Infrastructure\Twig\EscapeRoomExtension;
use Tilttek\EscapeRoomBundle\Infrastructure\Twig\KitComponents;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\Rooms;
use Tilttek\EscapeRoomBundle\TilttekEscapeRoomBundle;
use Tilttek\PuzzleBuilder\Contract\GuessLimiter;
use Tilttek\PuzzleBuilder\PuzzleEngine;

/**
 * The arguments, tags and visibility of each service the bundle registers.
 */
#[CoversClass(TilttekEscapeRoomBundle::class)]
#[CoversClass(EscapeRoomServices::class)]
final class EscapeRoomServicesTest extends TestCase
{
    public function testThePortsOfTheApplicationAreAliasedPublicly(): void
    {
        $container = $this->load([]);

        foreach ([GameStore::class, ResultStore::class, ScreenStore::class, RoomCatalog::class, EscapeRoomAccess::class, TeamNames::class, SeatLedger::class, SessionReservations::class] as $port) {
            self::assertTrue($container->getAlias($port)->isPublic(), $port);
        }

        self::assertTrue($container->hasDefinition(NoEscapeRoomAccess::class));
        self::assertTrue($container->hasDefinition(UnnamedTeams::class));
        self::assertTrue($container->getAlias(EscapeRoomAccess::class)->isPublic());
        self::assertFalse($container->getAlias(PartyBookings::class)->isPublic());
    }

    public function testTheServicesOfTheApplicationAreAliasedPublicly(): void
    {
        $container = $this->load(['room_catalog' => 'app.rooms', 'reservations' => 'app.reservations', 'access' => 'app.access', 'team_names' => 'app.names']);

        foreach ([RoomCatalog::class, SessionReservations::class, EscapeRoomAccess::class, TeamNames::class] as $port) {
            self::assertTrue($container->getAlias($port)->isPublic(), $port);
        }
    }

    public function testTheMemoryLedgerIsPublic(): void
    {
        $container = $this->load(['store' => 'memory']);

        self::assertTrue($container->getAlias(SeatLedger::class)->isPublic());
        self::assertSame(InMemorySeatLedger::class, (string) $container->getAlias(SeatLedger::class));
    }

    public function testTheDatabaseServicesGetTheirConnection(): void
    {
        $container = $this->load(['connection' => 'rooms']);
        $connection = new Reference('doctrine.dbal.rooms_connection');

        self::assertEquals([$connection], $container->getDefinition(DbalGameStore::class)->getArguments());
        self::assertEquals([$connection], $container->getDefinition(EscapeRoomSchemaListener::class)->getArguments());
        self::assertEquals([$connection, new Reference('clock')], $container->getDefinition(DbalSeatLedger::class)->getArguments());
        self::assertTrue($container->getAlias(SeatLedger::class)->isPublic());
    }

    public function testTheEngineTheRoomsAndTheBookerAreWired(): void
    {
        $container = $this->load([]);
        $events = new Reference(EventDispatcherInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $clock = new Reference('clock');

        $engine = $container->getDefinition(PuzzleEngine::class.'.escape_room');
        self::assertSame(PuzzleEngine::class, $engine->getClass());
        self::assertEquals([new Reference(GuessLimiter::class.'.escape_room'), $events, $clock], $engine->getArguments());

        $rooms = $container->getDefinition(EscapeRooms::class);
        self::assertEquals([new Reference(PuzzleEngine::class.'.escape_room'), $events, $clock], $rooms->getArguments());
        self::assertTrue($rooms->isPublic());

        $booker = $container->getDefinition(SessionBooker::class);
        self::assertEquals([new Reference(SessionReservations::class), $events, $clock], $booker->getArguments());
        self::assertTrue($booker->isPublic());

        self::assertEquals([new Reference(SeatLedger::class), null, $clock], $container->getDefinition(BookableSessionReservations::class)->getArguments());
        self::assertSame(['tilttek-escape-room'], $container->getDefinition(EscapeRoomTopics::class)->getArguments());
    }

    public function testTheGuessLimiterIsBuiltOnTheCache(): void
    {
        $container = $this->load([]);

        $factory = $container->getDefinition(EscapeRoomServices::GUESS_LIMITER_FACTORY);
        $storage = $factory->getArgument(1);
        self::assertInstanceOf(Definition::class, $storage);
        self::assertEquals([new Reference('cache.app')], $storage->getArguments());
        self::assertSame($storage, $container->getDefinition(EscapeRoomServices::GUESS_LIMITER_FACTORY.'.storage'));
        self::assertEquals([new Reference(EscapeRoomServices::GUESS_LIMITER_FACTORY)], $container->getDefinition(GuessLimiter::class.'.escape_room')->getArguments());
    }

    public function testTheDeskAndTheScreensAreWired(): void
    {
        $container = $this->load([]);

        $desk = $container->getDefinition(GameDesk::class);
        self::assertTrue($desk->isPublic());
        self::assertEquals(
            [new Reference(EscapeRooms::class), new Reference(RoomCatalog::class), new Reference(GameStore::class), new Reference(ResultStore::class), new Reference(ScreenStore::class), new Reference(PuzzleEngine::class.'.escape_room'), new Reference('clock'), null],
            $desk->getArguments(),
        );

        $twig = $container->getDefinition(EscapeRoomExtension::class);
        self::assertEquals([new Reference(KitComponents::class), 'tilttek_escape_room'], $twig->getArguments());
        self::assertSame([[]], $twig->getTag('twig.attribute_extension'));
        self::assertSame([[]], $twig->getTag('twig.runtime'));

        self::assertEquals(
            [new Reference(GameDesk::class), new Reference(EscapeRoomAccess::class), new Reference('twig'), new Reference('security.csrf.token_manager'), 'base.html.twig'],
            $container->getDefinition(Screens::class)->getArguments(),
        );
        self::assertEquals(
            [new Reference(Screens::class), new Reference(GameDesk::class), new Reference(Views::class), new Reference('router')],
            $this->controller($container, ConsoleController::class)->getArguments(),
        );
        self::assertEquals([new Reference(Screens::class), new Reference(Views::class), new Reference('router')], $this->controller($container, ScreenController::class)->getArguments());
        self::assertEquals([new Reference(Screens::class), new Reference(GameDesk::class), new Reference('router')], $this->controller($container, ConsentController::class)->getArguments());
        self::assertEquals(
            [new Reference(GameDesk::class), new Reference(RoomCatalog::class), new Reference(TeamNames::class), new Reference('twig'), 'base.html.twig', 10],
            $this->controller($container, LeaderboardController::class)->getArguments(),
        );
        self::assertEquals(
            [new Reference(EscapeRoomTopics::class), null, null, new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE), null],
            $container->getDefinition(ScreenSubscriptions::class)->getArguments(),
        );
    }

    public function testTheCommandTheHandlerAndThePrivacyModuleAreWired(): void
    {
        $container = $this->load(['schedule' => ['enabled' => true]]);

        $command = $container->getDefinition(ExpireCommand::class);
        self::assertEquals([new Reference(GameDesk::class)], $command->getArguments());
        self::assertSame(['console.command' => [['command' => 'tilttek:escape-room:expire', 'description' => 'Ends the games whose countdown reached zero']]], $command->getTags());

        self::assertTrue($container->getDefinition(ExpireDueHandler::class)->isPublic());

        $privacy = $container->getDefinition(EscapeRoomPrivacy::class);
        self::assertTrue($privacy->isPublic());
        self::assertSame([[]], $privacy->getTag(PhilPrivacyBundle::EXPORTER_TAG));
        self::assertSame([[]], $privacy->getTag(PhilPrivacyBundle::ERASER_TAG));
    }

    public function testTheCatalogOfTheConfigurationIsRegistered(): void
    {
        $container = $this->load(['rooms' => Rooms::config()]);

        self::assertTrue($container->hasDefinition(ConfiguredRoomCatalog::class));
    }

    private function controller(ContainerBuilder $container, string $class): Definition
    {
        $definition = $container->getDefinition($class);
        self::assertTrue($definition->isPublic());
        self::assertSame([[]], $definition->getTag('controller.service_arguments'));

        return $definition;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new TilttekEscapeRoomBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }
}
