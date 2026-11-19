<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\HubInterface;
use Tilttek\EscapeRoom\Contract\SeatLedger;
use Tilttek\EscapeRoom\Contract\SessionReservations;
use Tilttek\EscapeRoom\Reservation\BookableSessionReservations;
use Tilttek\EscapeRoom\Reservation\Contract\RoomBookables;
use Tilttek\EscapeRoomBundle\Application\ConfiguredRoomCatalog;
use Tilttek\EscapeRoomBundle\Application\Contract\EscapeRoomAccess;
use Tilttek\EscapeRoomBundle\Application\Contract\GameStore;
use Tilttek\EscapeRoomBundle\Application\Contract\PartyBookings;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;
use Tilttek\EscapeRoomBundle\Application\Contract\RoomCatalog;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenPublisher;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenStore;
use Tilttek\EscapeRoomBundle\Application\Contract\TeamNames;
use Tilttek\EscapeRoomBundle\Application\GameDesk;
use Tilttek\EscapeRoomBundle\Application\NoEscapeRoomAccess;
use Tilttek\EscapeRoomBundle\Application\UnnamedTeams;
use Tilttek\EscapeRoomBundle\Infrastructure\Console\ExpireCommand;
use Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection\EscapeRoomServices;
use Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\EscapeRoomBundle\Infrastructure\DependencyInjection\MercureHubPass;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalGameStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalResultStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalScreenStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\DbalSeatLedger;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomSchemaListener;
use Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomTables;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\LeaderboardController;
use Tilttek\EscapeRoomBundle\Infrastructure\Http\Views;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryGameStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryResultStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemoryScreenStore;
use Tilttek\EscapeRoomBundle\Infrastructure\Memory\InMemorySeatLedger;
use Tilttek\EscapeRoomBundle\Infrastructure\Mercure\MercureScreenPublisher;
use Tilttek\EscapeRoomBundle\Infrastructure\Mercure\ScreenSubscriptions;
use Tilttek\EscapeRoomBundle\Infrastructure\Privacy\EscapeRoomPrivacy;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\EscapeRoomSchedule;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\ExpireDue;
use Tilttek\EscapeRoomBundle\Infrastructure\Scheduler\ExpireDueHandler;
use Tilttek\EscapeRoomBundle\Infrastructure\Twig\KitComponents;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\RecordingHub;
use Tilttek\EscapeRoomBundle\Tests\Unit\Fixture\Rooms;
use Tilttek\EscapeRoomBundle\TilttekEscapeRoomBundle;
use Tilttek\PuzzleBuilder\Limiter\RateLimiterGuessLimiter;

#[CoversClass(TilttekEscapeRoomBundle::class)]
#[CoversClass(EscapeRoomServices::class)]
#[CoversClass(MercureHubPass::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(EscapeRoomSchemaListener::class)]
final class TilttekEscapeRoomBundleTest extends TestCase
{
    public function testTheServicesOnDoctrine(): void
    {
        $container = $this->load(['rooms' => Rooms::config()]);
        $connection = new Reference('doctrine.dbal.default_connection');

        self::assertSame('tilttek_escape_room', $this->extension()->getAlias());

        foreach ([GameStore::class => DbalGameStore::class, ResultStore::class => DbalResultStore::class, ScreenStore::class => DbalScreenStore::class] as $port => $store) {
            self::assertEquals([$connection], $container->getDefinition($store)->getArguments());
            self::assertSame([$store, true], [(string) $container->getAlias($port), $container->getAlias($port)->isPublic()]);
            self::assertSame([], $container->getDefinition($store)->getTags());
        }

        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(EscapeRoomSchemaListener::class)->getTags());
        self::assertSame(ConfiguredRoomCatalog::class, (string) $container->getAlias(RoomCatalog::class));
        self::assertSame(Rooms::config(), $container->getDefinition(ConfiguredRoomCatalog::class)->getArgument(0));
        self::assertSame(NoEscapeRoomAccess::class, (string) $container->getAlias(EscapeRoomAccess::class));
        self::assertSame(UnnamedTeams::class, (string) $container->getAlias(TeamNames::class));
        self::assertSame(BookableSessionReservations::class, (string) $container->getAlias(SessionReservations::class));
        self::assertSame(DbalSeatLedger::class, (string) $container->getAlias(SeatLedger::class));
        self::assertSame(DbalSeatLedger::class, (string) $container->getAlias(PartyBookings::class));
        self::assertEquals([new Reference(SeatLedger::class), null, new Reference('clock')], $container->getDefinition(BookableSessionReservations::class)->getArguments());
        self::assertSame(RateLimiterGuessLimiter::class, $container->getDefinition('Tilttek\PuzzleBuilder\Contract\GuessLimiter.escape_room')->getClass());
        self::assertSame(['id' => 'tilttek_escape_room_guesses', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '60 seconds'], $container->getDefinition(EscapeRoomServices::GUESS_LIMITER_FACTORY)->getArgument(0));
        self::assertNull($container->getDefinition(GameDesk::class)->getArgument(7));
        self::assertSame(HubInterface::class, $container->getParameter(MercureHubPass::HUB));
        self::assertFalse($container->getParameter(MercureHubPass::NAMED));
        self::assertFalse($container->hasDefinition(EscapeRoomSchedule::class));
        self::assertTrue($container->hasDefinition(ExpireCommand::class));
        self::assertEquals(
            [new Reference(GameStore::class), new Reference(ResultStore::class), new Reference(ScreenStore::class), 'member', new Reference(PartyBookings::class)],
            $container->getDefinition(EscapeRoomPrivacy::class)->getArguments(),
        );
        self::assertSame('confetti', $container->getDefinition(Views::class)->getArgument(3));
    }

    public function testTheServicesInMemory(): void
    {
        $container = $this->load(['store' => 'memory', 'celebration' => false, 'mercure' => ['poll' => 9]]);

        foreach ([GameStore::class => InMemoryGameStore::class, ResultStore::class => InMemoryResultStore::class, ScreenStore::class => InMemoryScreenStore::class, SeatLedger::class => InMemorySeatLedger::class] as $port => $store) {
            self::assertSame($store, (string) $container->getAlias($port));
            self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition($store)->getTags());
        }

        self::assertFalse($container->hasDefinition(EscapeRoomSchemaListener::class));
        self::assertNull($container->getDefinition(Views::class)->getArgument(3));
        self::assertSame(9, $container->getDefinition(Views::class)->getArgument(4));
    }

    public function testTheServicesOfTheApplication(): void
    {
        $container = $this->load([
            'room_catalog' => 'app.rooms',
            'access' => 'app.access',
            'team_names' => 'app.names',
            'reservations' => 'app.reservations',
            'guess_limiter' => 'app.limiter',
            'privacy' => ['member_kind' => 'user'],
            'leaderboard_size' => 20,
            'layout' => 'app.html.twig',
        ]);

        self::assertSame('app.rooms', (string) $container->getAlias(RoomCatalog::class));
        self::assertSame('app.access', (string) $container->getAlias(EscapeRoomAccess::class));
        self::assertSame('app.names', (string) $container->getAlias(TeamNames::class));
        self::assertSame('app.reservations', (string) $container->getAlias(SessionReservations::class));
        self::assertSame('app.limiter', (string) $container->getAlias('Tilttek\PuzzleBuilder\Contract\GuessLimiter.escape_room'));
        self::assertFalse($container->hasDefinition(ConfiguredRoomCatalog::class));
        self::assertFalse($container->hasDefinition(NoEscapeRoomAccess::class));
        self::assertFalse($container->hasAlias(PartyBookings::class));
        self::assertSame('user', $container->getDefinition(EscapeRoomPrivacy::class)->getArgument(3));
        self::assertNull($container->getDefinition(EscapeRoomPrivacy::class)->getArgument(4));
        self::assertSame(['app.html.twig', 20], \array_slice($container->getDefinition(LeaderboardController::class)->getArguments(), 4));
    }

    public function testTheRulesOfTheRoomsOnPhilReservation(): void
    {
        $container = $this->load(['room_bookables' => 'app.hours']);

        self::assertEquals(new Reference('app.hours'), $container->getDefinition(BookableSessionReservations::class)->getArgument(1));
        self::assertSame('app.hours', (string) $container->getAlias(RoomBookables::class));
    }

    public function testTheSchedule(): void
    {
        $container = $this->load(['schedule' => ['enabled' => true, 'frequency' => '1 minute', 'batch' => 50]]);

        self::assertSame(['1 minute'], $container->getDefinition(EscapeRoomSchedule::class)->getArguments());
        self::assertSame(['scheduler.schedule_provider' => [['name' => 'tilttek_escape_room']]], $container->getDefinition(EscapeRoomSchedule::class)->getTags());
        self::assertEquals([new Reference(GameDesk::class), 50], $container->getDefinition(ExpireDueHandler::class)->getArguments());
        self::assertSame(['messenger.message_handler' => [['handles' => ExpireDue::class]]], $container->getDefinition(ExpireDueHandler::class)->getTags());
    }

    public function testTheDefaultHubIsWiredWhenMercureIsThere(): void
    {
        $container = $this->load([]);
        $container->register(HubInterface::class, RecordingHub::class);
        new MercureHubPass()->process($container);

        self::assertSame(MercureScreenPublisher::class, (string) $container->getAlias(ScreenPublisher::class));
        self::assertEquals(new Reference(HubInterface::class), $container->getDefinition(MercureScreenPublisher::class)->getArgument(0));
        self::assertEquals(new Reference(ScreenPublisher::class), $container->getDefinition(GameDesk::class)->getArgument(7));
        self::assertEquals(new Reference(HubInterface::class), $container->getDefinition(ScreenSubscriptions::class)->getArgument(1));
        self::assertNotNull($container->getDefinition(ScreenSubscriptions::class)->getArgument(2));
    }

    public function testWithoutAHubTheScreensReadTheGameAtIntervals(): void
    {
        $container = $this->load([]);
        new MercureHubPass()->process($container);

        self::assertFalse($container->hasAlias(ScreenPublisher::class));
        self::assertNull($container->getDefinition(GameDesk::class)->getArgument(7));
        self::assertNull($container->getDefinition(ScreenSubscriptions::class)->getArgument(1));

        $disabled = $this->load(['mercure' => false]);
        $disabled->register(HubInterface::class, RecordingHub::class);
        new MercureHubPass()->process($disabled);
        self::assertNull($disabled->getParameter(MercureHubPass::HUB));
        self::assertFalse($disabled->hasAlias(ScreenPublisher::class));

        $empty = new ContainerBuilder();
        new MercureHubPass()->process($empty);
        self::assertFalse($empty->hasAlias(ScreenPublisher::class));
    }

    public function testAHubNamedIsWiredEvenBeforeItIsRegistered(): void
    {
        $container = $this->load(['mercure' => ['hub' => 'mercure.hub.rooms', 'hub_name' => 'rooms', 'topic_prefix' => 'rooms']]);
        new MercureHubPass()->process($container);

        self::assertTrue($container->getParameter(MercureHubPass::NAMED));
        self::assertEquals(new Reference('mercure.hub.rooms'), $container->getDefinition(MercureScreenPublisher::class)->getArgument(0));
        self::assertSame('rooms', $container->getDefinition(ScreenSubscriptions::class)->getArgument(4));
    }

    public function testTheKitIsNamedAfterTheToolkit(): void
    {
        $container = $this->load([]);
        $container->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $own = $this->load(['kit_subdirectory' => 'kit']);
        $own->setParameter(KitSubdirectoryPass::TOOLKIT_SUBDIRECTORY, 'ui');
        new KitSubdirectoryPass()->process($own);
        self::assertSame('kit', $own->getDefinition(KitComponents::class)->getArgument(0));

        $none = $this->load([]);
        new KitSubdirectoryPass()->process($none);
        self::assertSame('', $none->getDefinition(KitComponents::class)->getArgument(0));
        new KitSubdirectoryPass()->process(new ContainerBuilder());
    }

    public function testAScenarioIsAMap(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The scenario of a room is a map of its fields.');

        $this->load(['rooms' => ['crypt' => ['title' => 'The crypt', 'scenario' => 'crypt']]]);
    }

    public function testTheSchemaListenerAddsTheTablesOfItsConnection(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $listener = new EscapeRoomSchemaListener($connection);

        $event = new GenerateSchemaEventArgs($this->manager($connection), Schema::editor()->create());
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(EscapeRoomTables::GAMES));

        $event = new GenerateSchemaEventArgs($this->manager($other), Schema::editor()->create());
        $listener->postGenerateSchema($event);
        self::assertFalse($event->getSchema()->hasTable(EscapeRoomTables::GAMES));
    }

    public function testTheBundleAddsItsPasses(): void
    {
        $container = new ContainerBuilder();
        new TilttekEscapeRoomBundle()->build($container);
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        self::assertContains(KitSubdirectoryPass::class, $passes);
        self::assertContains(MercureHubPass::class, $passes);
    }

    private function manager(Connection $connection): EntityManagerInterface
    {
        $manager = self::createStub(EntityManagerInterface::class);
        $manager->method('getConnection')->willReturn($connection);

        return $manager;
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
        $extension = new TilttekEscapeRoomBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
