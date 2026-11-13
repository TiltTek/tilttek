<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\HubInterface;
use Tilttek\GameEngine\Contract\Game;
use Tilttek\GameEngine\Contract\LogStore;
use Tilttek\GameEngine\Contract\StateCache;
use Tilttek\GameEngine\Contract\TablePublisher;
use Tilttek\GameEngine\Engine;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Store\NullStateCache;
use Tilttek\GameEngine\Store\NullTablePublisher;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Application\AudienceViewerResolver;
use Tilttek\GameEngineBundle\Application\Contract\TableLock;
use Tilttek\GameEngineBundle\Application\Contract\ViewerResolver;
use Tilttek\GameEngineBundle\Application\GameTables;
use Tilttek\GameEngineBundle\Infrastructure\Cache\CacheStateCache;
use Tilttek\GameEngineBundle\Infrastructure\Console\SetupCommand;
use Tilttek\GameEngineBundle\Infrastructure\Console\VerifyCommand;
use Tilttek\GameEngineBundle\Infrastructure\DependencyInjection\GameEngineServices;
use Tilttek\GameEngineBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\DbalLogStore;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineSchemaListener;
use Tilttek\GameEngineBundle\Infrastructure\Http\TableController;
use Tilttek\GameEngineBundle\Infrastructure\Lock\SymfonyTableLock;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\MercureTablePublisher;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\TableTopic;
use Tilttek\GameEngineBundle\Infrastructure\Twig\GameEngineExtension;
use Tilttek\GameEngineBundle\Infrastructure\Twig\KitComponents;
use Tilttek\GameEngineBundle\Tests\Fixture\RollRace;
use Tilttek\GameEngineBundle\TilttekGameEngineBundle;

#[CoversClass(TilttekGameEngineBundle::class)]
#[CoversClass(GameEngineServices::class)]
final class TilttekGameEngineBundleTest extends TestCase
{
    public function testTheServicesByDefault(): void
    {
        $container = $this->load([]);

        self::assertSame('tilttek_game_engine', $this->extension()->getAlias());
        self::assertSame(
            ['service_container', Engine::class, Games::class, DbalLogStore::class, CacheStateCache::class, NullTablePublisher::class, Tables::class, GameTables::class, AudienceViewerResolver::class, TableController::class, VerifyCommand::class, SetupCommand::class, GameEngineSchemaListener::class, KitComponents::class, GameEngineExtension::class],
            array_keys($container->getDefinitions()),
        );
        self::assertSame([GameEngineServices::GAME_TAG => [[]]], $container->getAutoconfiguredInstanceof()[Game::class]->getTags());
        self::assertSame([GameEngineServices::class, 'games'], $container->getDefinition(Games::class)->getFactory());
        self::assertEquals([new TaggedIteratorArgument(GameEngineServices::GAME_TAG)], $container->getDefinition(Games::class)->getArguments());
        self::assertTrue($container->getDefinition(Games::class)->isPublic());
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalLogStore::class)->getArguments());
        self::assertSame(DbalLogStore::class, (string) $container->getAlias(LogStore::class));
        self::assertTrue($container->getAlias(LogStore::class)->isPublic());
        self::assertEquals([new Reference('cache.app'), null, null], $container->getDefinition(CacheStateCache::class)->getArguments());
        self::assertSame(CacheStateCache::class, (string) $container->getAlias(StateCache::class));
        self::assertTrue($container->getAlias(StateCache::class)->isPublic());
        self::assertSame(NullTablePublisher::class, (string) $container->getAlias(TablePublisher::class));
        self::assertTrue($container->getAlias(TablePublisher::class)->isPublic());
        self::assertEquals([new Reference(Games::class), new Reference(LogStore::class), new Reference(StateCache::class), new Reference(TablePublisher::class), new Reference(Engine::class)], $container->getDefinition(Tables::class)->getArguments());
        self::assertEquals([new Reference(Tables::class), null], $container->getDefinition(GameTables::class)->getArguments());
        self::assertTrue($container->getDefinition(GameTables::class)->isPublic());
        self::assertFalse($container->hasAlias(TableLock::class));
        self::assertSame([false], $container->getDefinition(AudienceViewerResolver::class)->getArguments());
        self::assertSame(AudienceViewerResolver::class, (string) $container->getAlias(ViewerResolver::class));
        self::assertEquals([new Reference(GameTables::class), new Reference(ViewerResolver::class), null], $container->getDefinition(TableController::class)->getArguments());
        self::assertTrue($container->getDefinition(TableController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(TableController::class)->isPublic());
        self::assertEquals([new Reference(GameTables::class), new Reference(Games::class), new Reference(Engine::class)], $container->getDefinition(VerifyCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(VerifyCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(SetupCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(SetupCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(GameEngineSchemaListener::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(GameEngineSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame([['method' => 'reset']], $container->getDefinition(Tables::class)->getTag('kernel.reset'));
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertEquals([new Reference(GameTables::class), 'tilttek_game_engine', new Reference(KitComponents::class), null], $container->getDefinition(GameEngineExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(GameEngineExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(GameEngineExtension::class)->hasTag('twig.runtime'));
        self::assertTrue($container->getDefinition(GameEngineExtension::class)->isPublic());
    }

    public function testEveryOptionChangesItsService(): void
    {
        $container = $this->load([[
            'store' => 'memory',
            'connection' => 'games',
            'cache' => ['pool' => 'cache.tables', 'lifetime' => 600],
            'lock' => ['factory' => 'lock.factory', 'ttl' => 12.5],
            'mercure' => ['enabled' => true, 'hub' => 'mercure.hub.games', 'topic' => '/tables/{table}', 'private' => false],
            'view' => ['resolver' => 'app.seats'],
            'roleplaying_scene' => true,
            'translation_domain' => 'games',
            'kit_subdirectory' => 'ui',
        ]]);

        self::assertTrue($container->getDefinition(RoleplayingScene::class)->hasTag(GameEngineServices::GAME_TAG));
        self::assertSame(InMemoryLogStore::class, (string) $container->getAlias(LogStore::class));
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryLogStore::class)->getTag('kernel.reset'));
        self::assertTrue($container->getAlias(LogStore::class)->isPublic());
        self::assertFalse($container->hasDefinition(DbalLogStore::class));
        self::assertFalse($container->hasDefinition(SetupCommand::class));
        self::assertFalse($container->hasDefinition(GameEngineSchemaListener::class));
        self::assertEquals([new Reference('cache.tables'), 600, new Reference('lock.factory')], $container->getDefinition(CacheStateCache::class)->getArguments());
        self::assertEquals([new Reference('lock.factory'), 12.5], $container->getDefinition(SymfonyTableLock::class)->getArguments());
        self::assertSame(SymfonyTableLock::class, (string) $container->getAlias(TableLock::class));
        self::assertEquals([new Reference(Tables::class), new Reference(TableLock::class)], $container->getDefinition(GameTables::class)->getArguments());
        self::assertSame(['/tables/{table}'], $container->getDefinition(TableTopic::class)->getArguments());
        self::assertEquals([new Reference('mercure.hub.games'), new Reference(TableTopic::class), false, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(MercureTablePublisher::class)->getArguments());
        self::assertSame(MercureTablePublisher::class, (string) $container->getAlias(TablePublisher::class));
        self::assertTrue($container->getAlias(TablePublisher::class)->isPublic());
        self::assertFalse($container->hasDefinition(NullTablePublisher::class));
        self::assertSame('app.seats', (string) $container->getAlias(ViewerResolver::class));
        self::assertFalse($container->hasDefinition(AudienceViewerResolver::class));
        self::assertEquals([new Reference(GameTables::class), new Reference(ViewerResolver::class), new Reference(TableTopic::class)], $container->getDefinition(TableController::class)->getArguments());
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertEquals([new Reference(GameTables::class), 'games', new Reference(KitComponents::class), new Reference(TableTopic::class)], $container->getDefinition(GameEngineExtension::class)->getArguments());

        $doctrine = $this->load([['connection' => 'games', 'cache' => ['pool' => null], 'mercure' => true, 'view' => ['audience' => true]]]);
        self::assertEquals([new Reference('doctrine.dbal.games_connection')], $doctrine->getDefinition(DbalLogStore::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'games']], $doctrine->getDefinition(GameEngineSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertSame(NullStateCache::class, (string) $doctrine->getAlias(StateCache::class));
        self::assertTrue($doctrine->getAlias(StateCache::class)->isPublic());
        self::assertFalse($doctrine->hasDefinition(CacheStateCache::class));
        self::assertTrue($doctrine->hasDefinition(NullStateCache::class));
        self::assertSame([true], $doctrine->getDefinition(AudienceViewerResolver::class)->getArguments());
        self::assertEquals([new Reference(HubInterface::class), new Reference(TableTopic::class), true, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $doctrine->getDefinition(MercureTablePublisher::class)->getArguments());
        self::assertSame([TableTopic::DEFAULT], $doctrine->getDefinition(TableTopic::class)->getArguments());
        self::assertFalse($doctrine->hasDefinition(RoleplayingScene::class));
    }

    public function testTheSmallestValuesTheConfigurationAllows(): void
    {
        $container = $this->load([['store' => 'doctrine', 'cache' => ['lifetime' => 1]]]);

        self::assertSame(DbalLogStore::class, (string) $container->getAlias(LogStore::class));
        self::assertEquals([new Reference('cache.app'), 1, null], $container->getDefinition(CacheStateCache::class)->getArguments());
    }

    public function testTheGamesAreGatheredByName(): void
    {
        self::assertSame(['roll-race', 'roleplaying-scene'], GameEngineServices::games(new ArrayIterator([new RollRace(), new RoleplayingScene()]))->names());
        self::assertSame([], GameEngineServices::games([])->names());
    }

    public function testATopicWithoutItsTableIsRefused(): void
    {
        foreach (['/tables', '/{table}/{table}'] as $topic) {
            try {
                $this->load([['mercure' => ['enabled' => true, 'topic' => $topic]]]);
                self::fail(\sprintf('The topic "%s" was accepted.', $topic));
            } catch (InvalidConfigurationException $exception) {
                self::assertStringContainsString('The topic of the tables holds "{table}" once', $exception->getMessage());
            }
        }

        foreach ([['cache' => ['lifetime' => 0]], ['lock' => ['ttl' => 0.5]], ['store' => 'files'], ['connection' => '']] as $config) {
            try {
                $this->load([$config]);
                self::fail(\sprintf('"%s" was accepted.', var_export($config, true)));
            } catch (InvalidConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheControllersAreMappedAndTheKitNamedAtCompilation(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['asset_mapper' => ['paths' => [TilttekGameEngineBundle::CONTROLLERS_DIR => '@tilttek/game-engine-bundle']]]], $container->getExtensionConfig('framework'));
        self::assertFileExists(TilttekGameEngineBundle::CONTROLLERS_DIR.'/game_table_controller.js');

        $without = new ContainerBuilder();
        $extension->prepend($without);
        self::assertSame([], $without->getExtensionConfig('framework'));

        $compiled = new ContainerBuilder();
        new TilttekGameEngineBundle()->build($compiled);
        self::assertContainsOnlyInstancesOf(KitSubdirectoryPass::class, array_filter($compiled->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
        self::assertCount(1, array_filter($compiled->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new TilttekGameEngineBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
