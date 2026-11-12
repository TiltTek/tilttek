<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Tilttek\Dice\DiceRoller;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Grammar\French;
use Tilttek\Generator\Grammar\Grammars;
use Tilttek\Generator\Grammar\Spanish;
use Tilttek\Generator\Limits;
use Tilttek\GeneratorBundle\Application\ApiAccess;
use Tilttek\GeneratorBundle\Application\Client;
use Tilttek\GeneratorBundle\Application\Contract\StateStore;
use Tilttek\GeneratorBundle\Application\MemoryTables;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\CachedCatalog;
use Tilttek\GeneratorBundle\Infrastructure\Catalog\GeneratorFactory;
use Tilttek\GeneratorBundle\Infrastructure\Console\CreateCommand;
use Tilttek\GeneratorBundle\Infrastructure\Console\DrawCommand;
use Tilttek\GeneratorBundle\Infrastructure\Console\ValidateCommand;
use Tilttek\GeneratorBundle\Infrastructure\DependencyInjection\GeneratorServices;
use Tilttek\GeneratorBundle\Infrastructure\DependencyInjection\PublicRateLimiterPass;
use Tilttek\GeneratorBundle\Infrastructure\Http\DrawController;
use Tilttek\GeneratorBundle\Infrastructure\Lock\SymfonyStateLock;
use Tilttek\GeneratorBundle\Infrastructure\RateLimit\SymfonyDrawRateLimiter;
use Tilttek\GeneratorBundle\Infrastructure\State\CacheStateStore;
use Tilttek\GeneratorBundle\Infrastructure\State\InMemoryStateStore;
use Tilttek\GeneratorBundle\Infrastructure\Twig\DrawExtension;
use Tilttek\GeneratorBundle\Tests\Fixture\Files;
use Tilttek\GeneratorBundle\TilttekGeneratorBundle;

#[CoversClass(TilttekGeneratorBundle::class)]
#[CoversClass(GeneratorServices::class)]
#[CoversClass(PublicRateLimiterPass::class)]
final class TilttekGeneratorBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('tilttek_generator', $this->extension()->getAlias());
        self::assertSame(
            ['service_container', CachedCatalog::class, Limits::class, Grammars::class, GeneratorFactory::class, Generator::class, CacheStateStore::class, MemoryTables::class, ApiAccess::class, DrawController::class, ValidateCommand::class, CreateCommand::class, DrawCommand::class, DrawExtension::class],
            array_keys($container->getDefinitions()),
        );
        self::assertSame([GeneratorServices::class, 'catalog'], $container->getDefinition(CachedCatalog::class)->getFactory());
        self::assertSame([null, '%kernel.project_dir%', '%kernel.cache_dir%/tilttek_generator/catalog.php', '%kernel.debug%', false, 'en'], $container->getDefinition(CachedCatalog::class)->getArguments());
        self::assertSame([8, 1000, 250, 10, 100, 2500, 1000], $container->getDefinition(Limits::class)->getArguments());
        self::assertSame([GeneratorServices::class, 'grammars'], $container->getDefinition(Grammars::class)->getFactory());
        self::assertSame([[], []], $container->getDefinition(Grammars::class)->getArguments());
        self::assertEquals([
            new Reference(CachedCatalog::class),
            new Reference(Limits::class),
            'en',
            new Reference(Grammars::class),
            new Reference(DiceRoller::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            '%kernel.cache_dir%/tilttek_generator/twig',
        ], $container->getDefinition(GeneratorFactory::class)->getArguments());
        self::assertEquals([new Reference(GeneratorFactory::class), 'generator'], $container->getDefinition(Generator::class)->getFactory());
        self::assertTrue($container->getDefinition(Generator::class)->isPublic());
        self::assertTrue($container->getDefinition(Generator::class)->isLazy());
        self::assertEquals([new Reference('cache.app')], $container->getDefinition(CacheStateStore::class)->getArguments());
        self::assertSame(CacheStateStore::class, (string) $container->getAlias(StateStore::class));
        self::assertTrue($container->getAlias(StateStore::class)->isPublic());
        self::assertEquals([new Reference(Generator::class), new Reference(StateStore::class), null], $container->getDefinition(MemoryTables::class)->getArguments());
        self::assertTrue($container->getDefinition(MemoryTables::class)->isPublic());
        self::assertSame([false, [], [], null], $container->getDefinition(ApiAccess::class)->getArguments());
        self::assertTrue($container->getDefinition(ApiAccess::class)->isPublic());
        self::assertEquals([new Reference(ApiAccess::class), new Reference(Generator::class), 20], $container->getDefinition(DrawController::class)->getArguments());
        self::assertTrue($container->getDefinition(DrawController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(DrawController::class)->isPublic());
        self::assertEquals([new Reference(GeneratorFactory::class)], $container->getDefinition(ValidateCommand::class)->getArguments());
        self::assertEquals([new Reference(CachedCatalog::class)], $container->getDefinition(CreateCommand::class)->getArguments());
        self::assertEquals([new Reference(GeneratorFactory::class), new Reference(StateStore::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(DrawCommand::class)->getArguments());

        foreach ([ValidateCommand::class, CreateCommand::class, DrawCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'));
        }

        self::assertEquals([new Reference(Generator::class)], $container->getDefinition(DrawExtension::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(DrawExtension::class)->getTags());
        self::assertTrue($container->getDefinition(DrawExtension::class)->isPublic());
    }

    public function testTheConfiguration(): void
    {
        $container = $this->load([
            'paths' => ['/srv/generators'],
            'locale' => 'fr',
            'aspirated' => ['hobgobelin'],
            'stressed_a' => ['Ánima'],
            'limits' => ['depth' => 1, 'picks' => 2, 'milliseconds' => 3, 'attempts' => 4, 'results' => 5, 'cells' => 6, 'draw_milliseconds' => 7],
            'cache' => false,
            'tables' => true,
            'tables_locale' => 'fr',
            'state' => ['pool' => 'cache.states', 'lock' => 'lock.factory'],
            'api' => [
                'enabled' => true,
                'max_count' => 50,
                'public' => ['name.elf'],
                'rate_limiter' => 'limiter.public',
                'clients' => [
                    'partner' => ['token' => 'secret', 'generators' => ['tavern'], 'rate_limiter' => 'limiter.partner'],
                    'other-site' => ['token' => 'other', 'generators' => ['*']],
                ],
            ],
        ]);

        self::assertSame([['/srv/generators'], '%kernel.project_dir%', '%kernel.cache_dir%/tilttek_generator/catalog.php', '%kernel.debug%', true, 'fr'], $container->getDefinition(CachedCatalog::class)->getArguments());
        self::assertEquals([new Reference('cache.states')], $container->getDefinition(CacheStateStore::class)->getArguments());
        self::assertEquals(new Definition(SymfonyStateLock::class, [new Reference('lock.factory')]), $container->getDefinition(MemoryTables::class)->getArgument(2));
        self::assertSame([1, 2, 3, 4, 5, 6, 7], $container->getDefinition(Limits::class)->getArguments());
        self::assertFalse($container->hasDefinition(GeneratorServices::PUBLIC_LIMITER));
        self::assertSame([['hobgobelin'], ['Ánima']], $container->getDefinition(Grammars::class)->getArguments());
        self::assertSame(['fr', false], [$container->getDefinition(GeneratorFactory::class)->getArgument(2), $container->getDefinition(GeneratorFactory::class)->getArgument(5)]);
        self::assertSame(5, $container->getDefinition(DrawController::class)->getArgument(2));
        self::assertEquals([
            true,
            ['name.elf'],
            [
                new Definition(Client::class, ['partner', 'secret', ['tavern'], new Definition(SymfonyDrawRateLimiter::class, [new Reference('limiter.partner'), new Reference('clock')])]),
                new Definition(Client::class, ['other-site', 'other', ['*'], null]),
            ],
            new Definition(SymfonyDrawRateLimiter::class, [new Reference('limiter.public'), new Reference('clock')]),
        ], $container->getDefinition(ApiAccess::class)->getArguments());
    }

    public function testTheStatesMayBeKeptInMemory(): void
    {
        $container = $this->load(['state' => ['store' => 'memory']]);

        self::assertFalse($container->hasDefinition(CacheStateStore::class));
        self::assertSame(['kernel.reset' => [['method' => 'reset']]], $container->getDefinition(InMemoryStateStore::class)->getTags());
        self::assertSame(InMemoryStateStore::class, (string) $container->getAlias(StateStore::class));
        self::assertTrue($container->getAlias(StateStore::class)->isPublic());
        self::assertTrue($container->hasDefinition(MemoryTables::class));
    }

    public function testTheStatesMayBeKeptByAStoreOfTheApplication(): void
    {
        $container = $this->load(['state' => ['store' => 'app.campaign_states']]);

        self::assertFalse($container->hasDefinition(CacheStateStore::class));
        self::assertFalse($container->hasDefinition(InMemoryStateStore::class));
        self::assertSame('app.campaign_states', (string) $container->getAlias(StateStore::class));
        self::assertTrue($container->getAlias(StateStore::class)->isPublic());
        self::assertTrue($container->hasDefinition(MemoryTables::class));
    }

    public function testAStoreIsNamed(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['state' => ['store' => '']]);
    }

    public function testEachLimitMayBeOne(): void
    {
        self::assertSame([1, 1, 1, 1, 1, 1, 1], $this->load(['limits' => ['depth' => 1, 'picks' => 1, 'milliseconds' => 1, 'attempts' => 1, 'results' => 1, 'cells' => 1, 'draw_milliseconds' => 1], 'api' => ['max_count' => 1, 'public_limit' => ['limit' => 1]]])->getDefinition(Limits::class)->getArguments());
    }

    public function testTheMaximumCountStaysWithinTheLimits(): void
    {
        self::assertSame(7, $this->load(['api' => ['max_count' => 7]])->getDefinition(DrawController::class)->getArgument(2));
    }

    public function testAClientNamesItsGenerators(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['api' => ['clients' => ['partner' => ['token' => 'secret', 'generators' => []]]]]);
    }

    public function testAClientHasAToken(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['api' => ['clients' => ['partner' => ['generators' => ['x']]]]]);
    }

    public function testTheLimitsAreAtLeastOne(): void
    {
        foreach (['depth', 'picks', 'milliseconds', 'attempts', 'results', 'cells', 'draw_milliseconds'] as $limit) {
            try {
                $this->load(['limits' => [$limit => 0]]);
                self::fail(\sprintf('The limit of %s took 0.', $limit));
            } catch (InvalidConfigurationException) {
            }
        }

        try {
            $this->load(['api' => ['public_limit' => ['limit' => 0]]]);
            self::fail('The default rate limit of the public draws took 0.');
        } catch (InvalidConfigurationException) {
        }

        $this->expectException(InvalidConfigurationException::class);
        $this->load(['api' => ['max_count' => 0]]);
    }

    public function testThePublicDrawsAreRateLimitedByDefault(): void
    {
        $container = $this->load(['api' => ['enabled' => true, 'public' => ['name.elf']]]);
        $limiter = $container->getDefinition(GeneratorServices::PUBLIC_LIMITER);

        self::assertSame(RateLimiterFactoryInterface::class, $limiter->getClass());
        self::assertEquals([new Reference('limiter_builder'), 'slidingWindow'], $limiter->getFactory());
        self::assertSame(['tilttek_generator_public', 60, '1 minute'], $limiter->getArguments());
        self::assertFalse($limiter->isPublic());
        self::assertEquals(new Definition(SymfonyDrawRateLimiter::class, [new Reference(GeneratorServices::PUBLIC_LIMITER), new Reference('clock')]), $container->getDefinition(ApiAccess::class)->getArgument(3));
        self::assertSame(['tilttek_generator_public', 5, '1 hour'], $this->load(['api' => ['enabled' => true, 'public' => ['name.elf'], 'public_limit' => ['limit' => 5, 'interval' => '1 hour']]])->getDefinition(GeneratorServices::PUBLIC_LIMITER)->getArguments());
    }

    public function testNoDefaultRateLimiterWithoutPublicDraws(): void
    {
        foreach ([['enabled' => true], ['public' => ['name.elf']], ['enabled' => true, 'public' => ['name.elf'], 'rate_limiter' => 'limiter.mine']] as $api) {
            $container = $this->load(['api' => $api]);

            self::assertFalse($container->hasDefinition(GeneratorServices::PUBLIC_LIMITER));
        }

        self::assertEquals(new Definition(SymfonyDrawRateLimiter::class, [new Reference('limiter.mine'), new Reference('clock')]), $this->load(['api' => ['enabled' => true, 'public' => ['name.elf'], 'rate_limiter' => 'limiter.mine']])->getDefinition(ApiAccess::class)->getArgument(3));
    }

    public function testTheDefaultRateLimiterNeedsTheRateLimiterBuilder(): void
    {
        $container = new ContainerBuilder();
        new TilttekGeneratorBundle()->build($container);
        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof PublicRateLimiterPass));

        $pass = new PublicRateLimiterPass();
        $pass->process(new ContainerBuilder());

        $built = new ContainerBuilder();
        $built->register(GeneratorServices::PUBLIC_LIMITER);
        $built->register('limiter_builder');

        $pass->process($built);
        $missing = new ContainerBuilder();
        $missing->register(GeneratorServices::PUBLIC_LIMITER);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The public draws of the API are rate limited by default: install symfony/rate-limiter and enable "framework.rate_limiter", or name a limiter in "tilttek_generator.api.rate_limiter".');

        $pass->process($missing);
    }

    public function testTheTablesAreInEnglishOrFrench(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['tables' => true, 'tables_locale' => 'de']);
    }

    public function testTheCatalogUsesTheProjectsDirectoryWhenNoPathIsGiven(): void
    {
        $directory = Files::directory();

        try {
            mkdir($directory.'/config');
            rename($directory.'/generators', $directory.'/config/generators');
            self::assertSame($directory.'/config/generators', GeneratorServices::catalog(null, $directory, $directory.'/catalog.php', false)->directory());
            self::assertSame(['name.syllable'], array_keys(GeneratorServices::catalog(null, $directory, $directory.'/catalog.php', false)->catalog()->lists()));
            self::assertNull(GeneratorServices::catalog(null, $directory.'/nothing', $directory.'/empty.php', false)->directory());
            self::assertSame([], GeneratorServices::catalog(null, $directory.'/nothing', $directory.'/empty.php', false)->catalog()->lists());
            self::assertNull(GeneratorServices::catalog([$directory.'/config/generators/names.yaml'], $directory, $directory.'/one.php', false)->directory());
            self::assertSame($directory.'/config/generators', GeneratorServices::catalog(null, $directory, $directory.'/tables.php', false, true)->directory());
            $lists = array_keys(GeneratorServices::catalog(null, $directory, $directory.'/tables.php', false, true)->catalog()->lists());
            self::assertContains('name.syllable', $lists);
            self::assertContains('table.shop.item', $lists);
            self::assertNotContains('table.shop.item', array_keys(GeneratorServices::catalog(null, $directory, $directory.'/plain.php', false)->catalog()->lists()));
            self::assertSame('smithy', GeneratorServices::catalog(null, $directory, $directory.'/tables.php', false, true)->catalog()->list('table.shop.kind')->entries[0]->text);
            self::assertSame('forge', GeneratorServices::catalog(null, $directory, $directory.'/french.php', false, true, 'fr')->catalog()->list('table.shop.kind')->entries[0]->text);
            file_put_contents($directory.'/extra.yaml', "lists:\n  extra.word: [x]\n");
            $lists = array_keys(GeneratorServices::catalog([$directory.'/config/generators', $directory.'/extra.yaml'], $directory, $directory.'/both.php', false, true)->catalog()->lists());
            self::assertContains('name.syllable', $lists);
            self::assertContains('extra.word', $lists);
            self::assertContains('table.shop.item', $lists);
        } finally {
            Files::remove($directory);
        }
    }

    public function testTheFrenchGrammarKnowsMoreAspiratedWords(): void
    {
        $grammar = GeneratorServices::grammars(['hobgobelin'])->for('fr');

        self::assertInstanceOf(French::class, $grammar);
        self::assertSame('le hobgobelin', $grammar->elide('le hobgobelin'));
        self::assertSame("l'arbre", GeneratorServices::grammars([])->for('fr_CA')->elide('le arbre'));
        self::assertSame('an apple', GeneratorServices::grammars([])->for('en')->elide('a apple'));
    }

    public function testTheSpanishGrammarKnowsMoreWordsWithAStressedA(): void
    {
        $grammar = GeneratorServices::grammars([], ['ánima'])->for('es');

        self::assertInstanceOf(Spanish::class, $grammar);
        self::assertSame('el ánima', $grammar->elide('la ánima'));
        self::assertSame('el agua', GeneratorServices::grammars([])->for('es')->elide('la agua'));
        self::assertSame('la ánima', GeneratorServices::grammars([])->for('es')->elide('la ánima'));
    }

    public function testTheControllersAreMappedForAssetMapper(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['asset_mapper' => ['paths' => [TilttekGeneratorBundle::CONTROLLERS_DIR => '@tilttek/generator-bundle']]]], $container->getExtensionConfig('framework'));
        self::assertFileExists(TilttekGeneratorBundle::CONTROLLERS_DIR.'/generator_controller.js');

        $alone = new ContainerBuilder();
        $extension->prepend($alone);
        self::assertSame([], $alone->getExtensionConfig('framework'));
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
        $extension = new TilttekGeneratorBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
