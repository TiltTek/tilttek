<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use LogicException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Mercure\Update;
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
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\DbalLogStore;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineSchemaListener;
use Tilttek\GameEngineBundle\Infrastructure\Http\TableController;
use Tilttek\GameEngineBundle\Infrastructure\Lock\SymfonyTableLock;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\MercureTablePublisher;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\TableTopic;
use Tilttek\GameEngineBundle\Infrastructure\Twig\GameEngineExtension;
use Tilttek\GameEngineBundle\Infrastructure\Twig\KitComponents;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the games tagged `tilttek_game_engine.game` — every service implementing `Contract\Game` —,
 * the log store on Doctrine DBAL or in memory, the state cache on a PSR-6 pool or none, the publisher on a Mercure hub
 * or none, the lock of the tables or none, the library's `Tables` and the bundle's `GameTables` on them, the viewer of
 * the endpoints, and — when their components are installed — the controller of the views, the commands, the schema
 * listener of the ORM and the Twig functions.
 *
 * @phpstan-type GameEngineConfig array{store: string, connection: string, cache: array{pool: ?string, lifetime: ?int}, lock: array{factory: ?string, ttl: float}, mercure: array{enabled: bool, hub: string, topic: string, private: bool}, view: array{audience: bool, resolver: ?string}, roleplaying_scene: bool, translation_domain: string, kit_subdirectory: ?string}
 */
final class GameEngineServices
{
    public const string GAME_TAG = 'tilttek_game_engine.game';

    private function __construct()
    {
    }

    /**
     * @param GameEngineConfig $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->registerForAutoconfiguration(Game::class)->addTag(self::GAME_TAG);

        if ($config['roleplaying_scene']) {
            $container->register(RoleplayingScene::class, RoleplayingScene::class)->addTag(self::GAME_TAG);
        }

        $container->register(Engine::class, Engine::class);
        $container->register(Games::class, Games::class)
            ->setFactory([self::class, 'games'])
            ->setArguments([new TaggedIteratorArgument(self::GAME_TAG)])
            ->setPublic(true);

        self::logStore($container, $config['store'], $config['connection']);
        self::stateCache($container, $config['cache'], $config['lock']['factory']);
        $topic = self::publisher($container, $config['mercure']);
        $lock = self::lock($container, $config['lock']);

        $container->register(Tables::class, Tables::class)->addTag('kernel.reset', ['method' => 'reset'])->setArguments([
            new Reference(Games::class),
            new Reference(LogStore::class),
            new Reference(StateCache::class),
            new Reference(TablePublisher::class),
            new Reference(Engine::class),
        ]);
        $container->register(GameTables::class, GameTables::class)
            ->setArguments([new Reference(Tables::class), $lock])
            ->setPublic(true);

        if (null === $config['view']['resolver']) {
            $container->register(AudienceViewerResolver::class, AudienceViewerResolver::class)->setArguments([$config['view']['audience']]);
            $container->setAlias(ViewerResolver::class, AudienceViewerResolver::class);
        } else {
            $container->setAlias(ViewerResolver::class, $config['view']['resolver']);
        }

        if (class_exists(JsonResponse::class)) {
            $container->register(TableController::class, TableController::class)
                ->setArguments([new Reference(GameTables::class), new Reference(ViewerResolver::class), $topic])
                ->addTag('controller.service_arguments')
                ->setPublic(true);
        }

        if (class_exists(Command::class)) {
            $container->register(VerifyCommand::class, VerifyCommand::class)
                ->setArguments([new Reference(GameTables::class), new Reference(Games::class), new Reference(Engine::class)])
                ->addTag('console.command');

            if ('doctrine' === $config['store']) {
                $container->register(SetupCommand::class, SetupCommand::class)
                    ->setArguments([self::connection($config['connection'])])
                    ->addTag('console.command');
            }
        }

        if ('doctrine' === $config['store'] && class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(GameEngineSchemaListener::class, GameEngineSchemaListener::class)
                ->setArguments([self::connection($config['connection'])])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        if (class_exists(AsTwigFunction::class)) {
            $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
            $container->register(GameEngineExtension::class, GameEngineExtension::class)
                ->setArguments([new Reference(GameTables::class), $config['translation_domain'], new Reference(KitComponents::class), $topic])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }
    }

    /**
     * The games of the application, by name.
     *
     * @param iterable<Game> $games
     */
    public static function games(iterable $games): Games
    {
        return new Games(...$games);
    }

    private static function connection(string $name): Reference
    {
        return new Reference(\sprintf('doctrine.dbal.%s_connection', $name));
    }

    private static function logStore(ContainerBuilder $container, string $store, string $connection): void
    {
        if ('memory' === $store) {
            $container->register(InMemoryLogStore::class, InMemoryLogStore::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(LogStore::class, InMemoryLogStore::class)->setPublic(true);

            return;
        }

        $container->register(DbalLogStore::class, DbalLogStore::class)->setArguments([self::connection($connection)]);
        $container->setAlias(LogStore::class, DbalLogStore::class)->setPublic(true);
    }

    /**
     * The state cache on its pool, its writes made under the lock factory of the tables when one is named.
     *
     * @param array{pool: ?string, lifetime: ?int} $cache
     */
    private static function stateCache(ContainerBuilder $container, array $cache, ?string $locks): void
    {
        if (null === $cache['pool'] || !interface_exists(CacheItemPoolInterface::class)) {
            $container->register(NullStateCache::class, NullStateCache::class);
            $container->setAlias(StateCache::class, NullStateCache::class)->setPublic(true);

            return;
        }

        $container->register(CacheStateCache::class, CacheStateCache::class)->setArguments([
            new Reference($cache['pool']),
            $cache['lifetime'],
            null === $locks ? null : new Reference($locks),
        ]);
        $container->setAlias(StateCache::class, CacheStateCache::class)->setPublic(true);
    }

    /**
     * The publisher on the Mercure hub, with the topic of the tables; none when Mercure is off.
     *
     * @param array{enabled: bool, hub: string, topic: string, private: bool} $mercure
     */
    private static function publisher(ContainerBuilder $container, array $mercure): ?Reference
    {
        if (!$mercure['enabled']) {
            $container->register(NullTablePublisher::class, NullTablePublisher::class);
            $container->setAlias(TablePublisher::class, NullTablePublisher::class)->setPublic(true);

            return null;
        }

        if (!class_exists(Update::class)) {
            throw new LogicException('Publishing the tables on a Mercure hub needs symfony/mercure: composer require symfony/mercure-bundle.');
        }

        $container->register(TableTopic::class, TableTopic::class)->setArguments([$mercure['topic']]);
        $container->register(MercureTablePublisher::class, MercureTablePublisher::class)->setArguments([
            new Reference($mercure['hub']),
            new Reference(TableTopic::class),
            $mercure['private'],
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
        $container->setAlias(TablePublisher::class, MercureTablePublisher::class)->setPublic(true);

        return new Reference(TableTopic::class);
    }

    /**
     * The lock of the tables on the application's lock factory; none when no factory is named.
     *
     * @param array{factory: ?string, ttl: float} $lock
     */
    private static function lock(ContainerBuilder $container, array $lock): ?Reference
    {
        if (null === $lock['factory']) {
            return null;
        }

        if (!class_exists(LockFactory::class)) {
            throw new LogicException('The lock of the tables needs symfony/lock: composer require symfony/lock.');
        }

        $container->register(SymfonyTableLock::class, SymfonyTableLock::class)->setArguments([new Reference($lock['factory']), $lock['ttl']]);
        $container->setAlias(TableLock::class, SymfonyTableLock::class);

        return new Reference(TableLock::class);
    }
}
