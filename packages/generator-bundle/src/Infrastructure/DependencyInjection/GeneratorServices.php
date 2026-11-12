<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\DependencyInjection;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Tilttek\Dice\DiceRoller;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Grammar\French;
use Tilttek\Generator\Grammar\Grammars;
use Tilttek\Generator\Grammar\Spanish;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Tables;
use Tilttek\GeneratorBundle\Application\ApiAccess;
use Tilttek\GeneratorBundle\Application\Client;
use Tilttek\GeneratorBundle\Application\Contract\StateStore;
use Tilttek\GeneratorBundle\Application\MemoryTables;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\CachedCatalog;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\GeneratorFactory;
use Tilttek\GeneratorBundle\Infrastructure\Console\CreateCommand;
use Tilttek\GeneratorBundle\Infrastructure\Console\DrawCommand;
use Tilttek\GeneratorBundle\Infrastructure\Console\ValidateCommand;
use Tilttek\GeneratorBundle\Infrastructure\Http\DrawController;
use Tilttek\GeneratorBundle\Infrastructure\Lock\SymfonyStateLock;
use Tilttek\GeneratorBundle\Infrastructure\RateLimit\SymfonyDrawRateLimiter;
use Tilttek\GeneratorBundle\Infrastructure\State\CacheStateStore;
use Tilttek\GeneratorBundle\Infrastructure\State\InMemoryStateStore;
use Tilttek\GeneratorBundle\Infrastructure\Twig\DrawExtension;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the cached catalog, the generator built from it on first use, the store of the states, its
 * optional lock and the tables with a memory, the access to the draw API and its rate limits, and — when their components are installed — the
 * controller of the API, the commands and the Twig function. The dice of `tilttek/dice-bundle` roll in the templates when it is installed.
 *
 * @phpstan-type Config array{paths: ?list<string>, locale: string, aspirated: list<string>, stressed_a: list<string>, limits: array{depth: int, picks: int, milliseconds: int, attempts: int, results: int, cells: int, draw_milliseconds: int}, cache: bool, tables: bool, tables_locale: string, state: array{store: string, pool: string, lock: ?string}, api: array{enabled: bool, max_count: int, public: list<string>, rate_limiter: ?string, public_limit: array{limit: int, interval: string}, clients: array<string, array{token: string, generators: list<string>, rate_limiter: ?string}>}}
 */
final class GeneratorServices
{
    /** The default rate limiter factory of the public draws, made by the rate limiter builder. */
    public const string PUBLIC_LIMITER = 'tilttek_generator.public_limiter';

    private function __construct()
    {
    }

    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->register(CachedCatalog::class, CachedCatalog::class)
            ->setFactory([self::class, 'catalog'])
            ->setArguments([$config['paths'], '%kernel.project_dir%', '%kernel.cache_dir%/tilttek_generator/catalog.php', '%kernel.debug%', $config['tables'], $config['tables_locale']]);
        $container->register(Limits::class, Limits::class)->setArguments(array_values($config['limits']));
        $container->register(Grammars::class, Grammars::class)->setFactory([self::class, 'grammars'])->setArguments([$config['aspirated'], $config['stressed_a']]);
        $container->register(GeneratorFactory::class, GeneratorFactory::class)->setArguments([
            new Reference(CachedCatalog::class),
            new Reference(Limits::class),
            $config['locale'],
            new Reference(Grammars::class),
            new Reference(DiceRoller::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            $config['cache'] ? '%kernel.cache_dir%/tilttek_generator/twig' : false,
        ]);
        $container->register(Generator::class, Generator::class)
            ->setFactory([new Reference(GeneratorFactory::class), 'generator'])
            ->setLazy(true)
            ->setPublic(true);

        if (self::store($container, $config['state'])) {
            $container->register(MemoryTables::class, MemoryTables::class)
                ->setArguments([new Reference(Generator::class), new Reference(StateStore::class), self::lock($config['state']['lock'])])
                ->setPublic(true);
        }

        $clients = [];

        foreach ($config['api']['clients'] as $id => $client) {
            $clients[] = new Definition(Client::class, [$id, $client['token'], $client['generators'], self::limiter($client['rate_limiter'])]);
        }

        $container->register(ApiAccess::class, ApiAccess::class)
            ->setArguments([$config['api']['enabled'], $config['api']['public'], $clients, self::limiter(self::publicLimiter($container, $config['api']['enabled'], $config['api']['public'], $config['api']['rate_limiter'], $config['api']['public_limit']))])
            ->setPublic(true);

        if (class_exists(JsonResponse::class)) {
            $container->register(DrawController::class, DrawController::class)
                ->setArguments([new Reference(ApiAccess::class), new Reference(Generator::class), min($config['api']['max_count'], $config['limits']['results'])])
                ->addTag('controller.service_arguments')
                ->setPublic(true);
        }

        if (class_exists(Command::class)) {
            $container->register(ValidateCommand::class, ValidateCommand::class)->setArguments([new Reference(GeneratorFactory::class)])->addTag('console.command');
            $container->register(CreateCommand::class, CreateCommand::class)->setArguments([new Reference(CachedCatalog::class)])->addTag('console.command');
            $container->register(DrawCommand::class, DrawCommand::class)
                ->setArguments([new Reference(GeneratorFactory::class), new Reference(StateStore::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->addTag('console.command');
        }

        if (class_exists(AsTwigFunction::class)) {
            $container->register(DrawExtension::class, DrawExtension::class)
                ->setArguments([new Reference(Generator::class)])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }
    }

    /**
     * The catalog of the configured paths, or of `config/generators` of the project when no path is configured and
     * that directory exists; and of the library's random tables in their language, when asked.
     *
     * @param ?list<string> $paths
     */
    public static function catalog(?array $paths, string $projectDirectory, string $cacheFile, bool $debug, bool $tables = false, string $tablesLocale = 'en'): CachedCatalog
    {
        $default = $projectDirectory.'/config/generators';
        $paths ??= is_dir($default) ? [$default] : [];

        return new CachedCatalog($tables ? [...$paths, Tables::directory($tablesLocale)] : $paths, $cacheFile, $debug);
    }

    /**
     * The built-in grammars, the French one knowing more words whose h is aspirated and the Spanish one more words
     * whose first syllable is a stressed a.
     *
     * @param list<string> $aspirated
     * @param list<string> $stressedA
     */
    public static function grammars(array $aspirated, array $stressedA = []): Grammars
    {
        return new Grammars(['fr' => new French($aspirated), 'es' => new Spanish($stressedA)]);
    }

    /**
     * The store of the states: in a cache pool when PSR-6 is installed, in memory, or the application's service; false
     * when the cache store has no cache to use.
     *
     * @param array{store: string, pool: string, lock: ?string} $state
     */
    private static function store(ContainerBuilder $container, array $state): bool
    {
        if ('memory' === $state['store']) {
            $container->register(InMemoryStateStore::class, InMemoryStateStore::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(StateStore::class, InMemoryStateStore::class)->setPublic(true);

            return true;
        }

        if ('cache' !== $state['store']) {
            $container->setAlias(StateStore::class, $state['store'])->setPublic(true);

            return true;
        }

        if (!interface_exists(CacheItemPoolInterface::class)) {
            return false;
        }

        $container->register(CacheStateStore::class, CacheStateStore::class)->setArguments([new Reference($state['pool'])]);
        $container->setAlias(StateStore::class, CacheStateStore::class)->setPublic(true);

        return true;
    }

    /**
     * The lock that keeps two draws on the same state apart, on the application's lock factory; none when no factory is
     * named.
     */
    private static function lock(?string $factory): ?Definition
    {
        return null === $factory ? null : new Definition(SymfonyStateLock::class, [new Reference($factory)]);
    }

    /**
     * The rate limiter of the public draws: the one the configuration names, or — when the API is enabled and opens
     * generators to anyone — the bundle's own, by address, on the rate limiter builder.
     *
     * @param list<string>                        $public
     * @param array{limit: int, interval: string} $limit
     */
    private static function publicLimiter(ContainerBuilder $container, bool $enabled, array $public, ?string $named, array $limit): ?string
    {
        if (null !== $named || !$enabled || [] === $public) {
            return $named;
        }

        $container->register(self::PUBLIC_LIMITER, RateLimiterFactoryInterface::class)
            ->setFactory([new Reference(PublicRateLimiterPass::BUILDER), 'slidingWindow'])
            ->setArguments(['tilttek_generator_public', $limit['limit'], $limit['interval']]);

        return self::PUBLIC_LIMITER;
    }

    private static function limiter(?string $service): ?Definition
    {
        return null === $service ? null : new Definition(SymfonyDrawRateLimiter::class, [new Reference($service), new Reference('clock')]);
    }
}
