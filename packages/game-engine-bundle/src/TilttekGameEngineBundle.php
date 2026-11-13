<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle;

use Override;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Mercure\HubInterface;
use Tilttek\GameEngineBundle\Infrastructure\DependencyInjection\GameEngineServices;
use Tilttek\GameEngineBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\TableTopic;

/**
 * Online tables for a Symfony application, on `tilttek/game-engine`:
 *
 * ```yaml
 * tilttek_game_engine:
 *     store: doctrine
 *     connection: default
 *     cache: { pool: cache.app }
 *     lock: { factory: lock.factory }
 *     mercure: { enabled: true }
 *     view: { resolver: App\Game\SeatOfTheMember }
 * ```
 *
 * The games are the services that implement `Contract\Game`. The logs of the tables are kept by Doctrine DBAL — or in
 * memory for tests —, their states cached in a PSR-6 pool as plain values, each move made under the lock of its table
 * when a lock factory is named, and every change published on the Mercure topic of its table, privately, with
 * identifiers only. The endpoints give the view of the seat the application's resolver names, and the log of a table
 * whose game is over.
 *
 * @phpstan-import-type GameEngineConfig from GameEngineServices
 */
final class TilttekGameEngineBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'tilttek_game_engine';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the logs of the tables are kept: "doctrine", or "memory" for tests.')
                    ->values(['doctrine', 'memory'])
                    ->defaultValue('doctrine')
                ->end()
                ->scalarNode('connection')
                    ->info('The Doctrine DBAL connection of the "doctrine" store.')
                    ->cannotBeEmpty()
                    ->defaultValue('default')
                ->end()
                ->arrayNode('cache')
                    ->info('Where the current states of the tables are cached, to spare a replay of the log at each read.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('pool')
                            ->info('The PSR-6 cache pool shared by every process; null replays the log at every read.')
                            ->defaultValue('cache.app')
                        ->end()
                        ->integerNode('lifetime')
                            ->info('The seconds a state is kept after it was cached; as long as the pool keeps it when null.')
                            ->min(1)
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('lock')
                    ->info('The lock that keeps two moves at one table apart; without it, the second is refused as a concurrent move.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('factory')
                            ->info('The service of a Symfony lock factory, such as lock.factory; none by default.')
                            ->defaultNull()
                        ->end()
                        ->floatNode('ttl')
                            ->info('The seconds a lock is kept when the process that holds it dies.')
                            ->min(1.0)
                            ->defaultValue(30.0)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('mercure')
                    ->info('Each change published on the Mercure topic of its table, with identifiers only.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('hub')
                            ->info('The service of the Mercure hub.')
                            ->cannotBeEmpty()
                            ->defaultValue(HubInterface::class)
                        ->end()
                        ->scalarNode('topic')
                            ->info('The topic of each table, {table} standing for its identifier.')
                            ->cannotBeEmpty()
                            ->defaultValue(TableTopic::DEFAULT)
                            ->validate()
                                ->ifTrue(static fn (string $topic): bool => 1 !== substr_count($topic, TableTopic::PLACEHOLDER))
                                ->thenInvalid('The topic of the tables holds "{table}" once: %s.')
                            ->end()
                        ->end()
                        ->booleanNode('private')
                            ->info('Publishes privately, to the subscribers the hub authorized for the topic only.')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('view')
                    ->info('Who reads a table through the endpoints.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('resolver')
                            ->info('The service implementing ViewerResolver, which names the seat of the current player; none by default.')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('audience')
                            ->info('Without a resolver, lets anyone read the audience view of every table; no one reads a table otherwise.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('roleplaying_scene')
                    ->info("Registers the library's ready game for the phases of a role-playing scene.")
                    ->defaultFalse()
                ->end()
                ->scalarNode('translation_domain')
                    ->info("The translation domain of the template's texts.")
                    ->cannotBeEmpty()
                    ->defaultValue('tilttek_game_engine')
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('The subdirectory of the components where the recipes of the kit are installed; that of the Symfony UX Toolkit when null.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework') && interface_exists(AssetMapperInterface::class)) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [self::CONTROLLERS_DIR => '@tilttek/game-engine-bundle']]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var GameEngineConfig $config */
        GameEngineServices::register($builder, $config);
    }
}
