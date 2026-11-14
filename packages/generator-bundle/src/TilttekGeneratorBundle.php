<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle;

use Override;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tilttek\Generator\Tables;
use Tilttek\GeneratorBundle\Infrastructure\DependencyInjection\GeneratorServices;
use Tilttek\GeneratorBundle\Infrastructure\DependencyInjection\PublicRateLimiterPass;

/**
 * Generators for a Symfony application, on `tilttek/generator`:
 *
 * ```yaml
 * tilttek_generator:
 *     paths: ['%kernel.project_dir%/config/generators']
 *     locale: fr
 *     tables: true
 *     state: { pool: cache.generator_states }
 *     api:
 *         enabled: true
 *         public: [name.elf]
 *         clients:
 *             partner: { token: '%env(GENERATOR_PARTNER_TOKEN)%', generators: [tavern], rate_limiter: limiter.generator_partner }
 * ```
 *
 * The lists and the generators are read from the files of the paths — and the library's random tables, when asked —
 * and kept in the cache; the generator checks them on first use, and Twig compiles their templates into files of the
 * cache, which it loads rather than evaluates. The tables with a memory keep their states in a cache
 * pool, in memory, or in a store of the application, behind a lock when one is named. The draw API is off until it is
 * enabled, and opens only the generators named, to anyone or to the clients that show their token, under the rate
 * limits of `framework.rate_limiter`: the public draws are limited by address by default, sixty a minute.
 */
final class TilttekGeneratorBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    /**
     * The default directory of Twig's compiled templates.
     */
    public const string TWIG_CACHE = '%kernel.cache_dir%/tilttek_generator/twig';

    #[Override]
    protected string $extensionAlias = 'tilttek_generator';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('paths')
                    ->info('Files and directories of YAML and JSON documents; config/generators of the project when it exists and no path is given.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultNull()
                ->end()
                ->scalarNode('locale')
                    ->info('The language of the generators that do not name theirs.')
                    ->cannotBeEmpty()
                    ->defaultValue('en')
                ->end()
                ->arrayNode('aspirated')
                    ->info('More French words whose h is aspirated, which keep their article whole.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('stressed_a')
                    ->info('More Spanish feminine words whose first syllable is a stressed a, which take el and un.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->arrayNode('limits')
                    ->info('The bounds that stop a generator gone wild.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('depth')->min(1)->defaultValue(8)->end()
                        ->integerNode('picks')->min(1)->defaultValue(1000)->end()
                        ->integerNode('milliseconds')->min(1)->defaultValue(250)->end()
                        ->integerNode('attempts')->min(1)->defaultValue(10)->end()
                        ->integerNode('results')->min(1)->defaultValue(100)->end()
                        ->integerNode('cells')->min(1)->defaultValue(2500)->end()
                        ->integerNode('draw_milliseconds')->info('The time of a whole draw: every result, and every attempt of a unique draw.')->min(1)->defaultValue(1000)->end()
                        ->integerNode('steps')->info('The turns of every loop and the assignments of one result.')->min(1)->defaultValue(10_000)->end()
                        ->integerNode('bytes')->info('The longest text a replace or a join returns, in bytes.')->min(1)->defaultValue(65_536)->end()
                        ->integerNode('megabytes')->info('The memory one result may grow by, checked at each step.')->min(1)->defaultValue(16)->end()
                    ->end()
                ->end()
                ->scalarNode('cache')
                    ->info("The directory of Twig's compiled templates, loaded from their files rather than by eval().")
                    ->defaultValue(self::TWIG_CACHE)
                    ->beforeNormalization()
                        ->ifTrue(static fn (mixed $cache): bool => true === $cache)
                        ->then(static fn (): string => self::TWIG_CACHE)
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (mixed $cache): bool => !\is_string($cache) || '' === trim($cache))
                        ->thenInvalid('The Twig cache is a directory: templates compiled without one would be loaded by eval().')
                    ->end()
                ->end()
                ->booleanNode('tables')
                    ->info("Loads the library's random tables too, named table.<name>.")
                    ->defaultFalse()
                ->end()
                ->enumNode('tables_locale')
                    ->info('The language of the random tables: "en" or "fr".')
                    ->values(Tables::LOCALES)
                    ->defaultValue('en')
                ->end()
                ->arrayNode('state')
                    ->info('Where the tables with a memory keep their states between draws.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('store')
                            ->info('"cache", "memory" for tests, or the service of a store of the application.')
                            ->cannotBeEmpty()
                            ->defaultValue('cache')
                        ->end()
                        ->scalarNode('pool')
                            ->info('The PSR-6 cache pool of the "cache" store; one on Doctrine DBAL or Valkey keeps the states across deployments.')
                            ->cannotBeEmpty()
                            ->defaultValue('cache.app')
                        ->end()
                        ->scalarNode('lock')
                            ->info('The service of a Symfony lock factory, such as lock.factory, that keeps two draws on the same state apart; none by default.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('api')
                    ->info('The draw API: off by default; only the generators it names are drawn, never their lists.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->integerNode('max_count')->info('The most results of one draw, within limits.results.')->min(1)->defaultValue(20)->end()
                        ->arrayNode('public')
                            ->info('The generators anyone may draw, without a token.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                        ->scalarNode('rate_limiter')
                            ->info('The rate limiter factory service of the public draws, counted by address; the bundle\'s own, by "public_limit", when null.')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('public_limit')
                            ->info('The default rate limit of the public draws, by address, on the rate limiter builder of symfony/rate-limiter.')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('limit')->min(1)->defaultValue(60)->end()
                                ->scalarNode('interval')->cannotBeEmpty()->defaultValue('1 minute')->end()
                            ->end()
                        ->end()
                        ->arrayNode('clients')
                            ->info('The sites and applications that draw with a token, by name.')
                            ->useAttributeAsKey('id')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('token')->isRequired()->cannotBeEmpty()->end()
                                    ->arrayNode('generators')
                                        ->info('The generators open to the client; "*" opens them all.')
                                        ->isRequired()
                                        ->requiresAtLeastOneElement()
                                        ->scalarPrototype()->cannotBeEmpty()->end()
                                    ->end()
                                    ->scalarNode('rate_limiter')->defaultNull()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PublicRateLimiterPass());
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework') && interface_exists(AssetMapperInterface::class)) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [self::CONTROLLERS_DIR => '@tilttek/generator-bundle']]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{paths: ?list<string>, locale: string, aspirated: list<string>, stressed_a: list<string>, limits: array{depth: int, picks: int, milliseconds: int, attempts: int, results: int, cells: int, draw_milliseconds: int, steps: int, bytes: int, megabytes: int}, cache: string, tables: bool, tables_locale: string, state: array{store: string, pool: string, lock: ?string}, api: array{enabled: bool, max_count: int, public: list<string>, rate_limiter: ?string, public_limit: array{limit: int, interval: string}, clients: array<string, array{token: string, generators: list<string>, rate_limiter: ?string}>}} $config */
        GeneratorServices::register($builder, $config);
    }
}
