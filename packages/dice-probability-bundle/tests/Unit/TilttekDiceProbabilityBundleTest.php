<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Unit;

use LogicException;
use Phil\RateLimit\RateLimitGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Limits;
use Tilttek\DiceBundle\TilttekDiceBundle;
use Tilttek\DiceProbability\Probabilities;
use Tilttek\DiceProbability\Settings;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;
use Tilttek\DiceProbabilityBundle\Application\GroupSearch;
use Tilttek\DiceProbabilityBundle\Application\Rounding;
use Tilttek\DiceProbabilityBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\DiceProbabilityBundle\Infrastructure\DependencyInjection\ProbabilityServices;
use Tilttek\DiceProbabilityBundle\Infrastructure\RateLimiter\ComputationThrottle;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\Components\DistributionComponent;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\KitComponents;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\ProbabilityExtension;
use Tilttek\DiceProbabilityBundle\TilttekDiceProbabilityBundle;

#[CoversClass(TilttekDiceProbabilityBundle::class)]
#[CoversClass(ProbabilityServices::class)]
final class TilttekDiceProbabilityBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('tilttek_dice_probability', $this->extension()->getAlias());
        self::assertSame(['service_container', Settings::class, ProbabilityServices::LIMITS, Probabilities::class, GroupSearch::class, DiceStatistics::class, KitComponents::class, ProbabilityExtension::class, DistributionComponent::class, ProbabilityServices::LIMITER_STORAGE, ProbabilityServices::LIMITER, ComputationThrottle::class], array_keys($container->getDefinitions()));
        self::assertSame([10, 100_000, 5_000, 0, 50_000, 2_000_000], $container->getDefinition(Settings::class)->getArguments());
        self::assertFalse($container->getDefinition(Settings::class)->isPublic());
        self::assertSame([ProbabilityServices::class, 'limits'], $container->getDefinition(ProbabilityServices::LIMITS)->getFactory());
        self::assertEquals([new Reference(Limits::class), 50], $container->getDefinition(ProbabilityServices::LIMITS)->getArguments());
        self::assertSame(Limits::class, $container->getDefinition(ProbabilityServices::LIMITS)->getClass());
        self::assertFalse($container->getDefinition(ProbabilityServices::LIMITS)->isPublic());
        self::assertEquals([new Reference(CustomDice::class), new Reference(ProbabilityServices::LIMITS), new Reference(Settings::class)], $container->getDefinition(Probabilities::class)->getArguments());
        self::assertTrue($container->getDefinition(Probabilities::class)->isPublic());
        self::assertEquals([new Reference(ProbabilityServices::LIMITS), 5_000, 2_000_000], $container->getDefinition(GroupSearch::class)->getArguments());
        self::assertFalse($container->getDefinition(GroupSearch::class)->isPublic());
        self::assertEquals([new Reference(DiceRoller::class), new Reference(Probabilities::class), Rounding::Floor, 500, new Reference(GroupSearch::class)], $container->getDefinition(DiceStatistics::class)->getArguments());
        self::assertTrue($container->getDefinition(DiceStatistics::class)->isPublic());
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertFalse($container->getDefinition(KitComponents::class)->isPublic());
        self::assertEquals([new Reference(DiceStatistics::class), new Reference(KitComponents::class)], $container->getDefinition(ProbabilityExtension::class)->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $container->getDefinition(ProbabilityExtension::class)->getTags());
        self::assertTrue($container->getDefinition(ProbabilityExtension::class)->isPublic());
        self::assertEquals([new Reference(DiceStatistics::class), new Reference(ComputationThrottle::class)], $container->getDefinition(DistributionComponent::class)->getArguments());
        self::assertEquals([new Reference(ProbabilityServices::LIMITER), new Reference('request_stack'), '%kernel.secret%'], $container->getDefinition(ComputationThrottle::class)->getArguments());
        self::assertFalse($container->getDefinition(ComputationThrottle::class)->isPublic());
        self::assertSame(RateLimiterFactory::class, $container->getDefinition(ProbabilityServices::LIMITER)->getClass());
        self::assertEquals([['id' => 'tilttek_dice_probability', 'policy' => 'sliding_window', 'limit' => 60, 'interval' => '1 minute'], new Reference(ProbabilityServices::LIMITER_STORAGE)], $container->getDefinition(ProbabilityServices::LIMITER)->getArguments());
        self::assertSame(CacheStorage::class, $container->getDefinition(ProbabilityServices::LIMITER_STORAGE)->getClass());
        self::assertEquals([new Reference('cache.app')], $container->getDefinition(ProbabilityServices::LIMITER_STORAGE)->getArguments());
        self::assertSame('tilttek_dice_probability.limiter', ProbabilityServices::LIMITER);
        self::assertSame('tilttek_dice_probability.limiter.storage', ProbabilityServices::LIMITER_STORAGE);
        self::assertTrue($container->getDefinition(DistributionComponent::class)->isAutoconfigured());
        self::assertFalse($container->getDefinition(DistributionComponent::class)->isPublic());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['explosion_depth' => 3, 'work_limit' => 1_000, 'samples' => 50, 'seed' => -4, 'roll_limit' => 700, 'max_dice' => 12, 'max_outcomes' => 40, 'group_work_limit' => 9_000, 'rounding' => 'ceil', 'rate_limit' => ['limit' => 5, 'interval' => '10 seconds', 'cache_pool' => 'cache.limits']]);

        self::assertSame([3, 1_000, 50, -4, 700, 9_000], $container->getDefinition(Settings::class)->getArguments());
        self::assertSame(12, $container->getDefinition(ProbabilityServices::LIMITS)->getArgument(1));
        self::assertSame(Rounding::Ceil, $container->getDefinition(DiceStatistics::class)->getArgument(2));
        self::assertSame(40, $container->getDefinition(DiceStatistics::class)->getArgument(3));
        self::assertSame([50, 9_000], \array_slice($container->getDefinition(GroupSearch::class)->getArguments(), 1));
        self::assertEquals([['id' => 'tilttek_dice_probability', 'policy' => 'sliding_window', 'limit' => 5, 'interval' => '10 seconds'], new Reference(ProbabilityServices::LIMITER_STORAGE)], $container->getDefinition(ProbabilityServices::LIMITER)->getArguments());
        self::assertEquals([new Reference('cache.limits')], $container->getDefinition(ProbabilityServices::LIMITER_STORAGE)->getArguments());
        self::assertSame(['kit'], $this->load(['kit_subdirectory' => 'kit'])->getDefinition(KitComponents::class)->getArguments());
    }

    public function testTheRateLimitMayUseAFactoryOfTheApplication(): void
    {
        $container = $this->load(['rate_limit' => ['limiter' => 'app.limiter']]);

        self::assertFalse($container->hasDefinition(ProbabilityServices::LIMITER));
        self::assertFalse($container->hasDefinition(ProbabilityServices::LIMITER_STORAGE));
        self::assertEquals(new Reference('app.limiter'), $container->getDefinition(ComputationThrottle::class)->getArgument(0));
    }

    public function testTheRateLimitMayBeTurnedOff(): void
    {
        $container = $this->load(['rate_limit' => false]);

        self::assertFalse($container->hasDefinition(ComputationThrottle::class));
        self::assertFalse($container->hasDefinition(ProbabilityServices::LIMITER));
        self::assertEquals([new Reference(DiceStatistics::class), null], $container->getDefinition(DistributionComponent::class)->getArguments());
    }

    public function testTheRateLimitNeedsTheRateLimiter(): void
    {
        $config = ['explosion_depth' => 10, 'work_limit' => 100_000, 'samples' => 5_000, 'seed' => 0, 'roll_limit' => 50_000, 'max_dice' => 50, 'max_outcomes' => 500, 'group_work_limit' => 2_000_000, 'rounding' => 'floor', 'kit_subdirectory' => null];
        $off = ['enabled' => false, 'limit' => 60, 'interval' => '1 minute', 'cache_pool' => 'cache.app', 'limiter' => null, 'single_server' => false];
        $container = new ContainerBuilder();
        ProbabilityServices::register($container, [...$config, 'rate_limit' => $off], false);
        self::assertFalse($container->hasDefinition(ComputationThrottle::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('tilttek_dice_probability.rate_limit needs symfony/rate-limiter: install it, or set "enabled" to false.');

        ProbabilityServices::register(new ContainerBuilder(), [...$config, 'rate_limit' => ['enabled' => true] + $off], false);
    }

    public function testTheRateLimiterIsThereByDefault(): void
    {
        $config = ['explosion_depth' => 10, 'work_limit' => 100_000, 'samples' => 5_000, 'seed' => 0, 'roll_limit' => 50_000, 'max_dice' => 50, 'max_outcomes' => 500, 'group_work_limit' => 2_000_000, 'rounding' => 'floor', 'kit_subdirectory' => null];
        $container = new ContainerBuilder();
        ProbabilityServices::register($container, [...$config, 'rate_limit' => ['enabled' => true, 'limit' => 60, 'interval' => '1 minute', 'cache_pool' => 'cache.app', 'limiter' => null, 'single_server' => false]]);

        self::assertTrue($container->hasDefinition(ComputationThrottle::class));
    }

    public function testTheBundleNamesTheComponentsOfTheKitWhenTheContainerIsCompiled(): void
    {
        $container = new ContainerBuilder();
        new TilttekDiceProbabilityBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof RateLimitGuard));
    }

    public function testThePoolOfTheRateLimitIsWatchedUnlessTheApplicationRunsOnOneServer(): void
    {
        self::assertSame(['tilttek_dice_probability.rate_limit.cache_pool' => ['store' => 'cache.limits', 'switch' => 'enabled']], RateLimitGuard::watched($this->load(['rate_limit' => ['cache_pool' => 'cache.limits']])));
        self::assertSame([], RateLimitGuard::watched($this->load(['rate_limit' => ['single_server' => true]])));
        self::assertSame([], RateLimitGuard::watched($this->load(['rate_limit' => ['limiter' => 'app.limiter']])));
        self::assertSame([], RateLimitGuard::watched($this->load(['rate_limit' => false])));
    }

    public function testALocalPoolIsRefusedInProduction(): void
    {
        $container = $this->load([]);
        $container->setParameter('kernel.environment', 'prod');
        $container->register('cache.app', FilesystemAdapter::class);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('tilttek_dice_probability.rate_limit.cache_pool: the store "cache.app" is local to each server');

        new RateLimitGuard()->process($container);
    }

    public function testAnUnknownRoundingIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The value "sideways" is not allowed for path "tilttek_dice_probability.rounding". Permissible values: "floor", "ceil", "round", "towards_zero", "away_from_zero"');

        $this->load(['rounding' => 'sideways']);
    }

    public function testEachBoundIsAtLeastOne(): void
    {
        foreach (['explosion_depth', 'work_limit', 'samples', 'roll_limit', 'max_dice', 'max_outcomes', 'group_work_limit'] as $bound) {
            try {
                $this->load([$bound => 0]);
                self::fail(\sprintf('%s is at least 1.', $bound));
            } catch (InvalidConfigurationException $invalid) {
                self::assertStringContainsString(\sprintf('"tilttek_dice_probability.%s"', $bound), $invalid->getMessage());
            }
        }

        $container = $this->load(['explosion_depth' => 1, 'work_limit' => 1, 'samples' => 1, 'roll_limit' => 1, 'max_dice' => 1, 'max_outcomes' => 1, 'group_work_limit' => 1, 'rate_limit' => ['limit' => 1]]);
        self::assertSame(1, $container->getDefinition(GroupSearch::class)->getArgument(2));

        foreach ([['limit' => 0], ['interval' => ''], ['cache_pool' => '']] as $rateLimit) {
            try {
                $this->load(['rate_limit' => $rateLimit]);
                self::fail(\sprintf('%s is refused.', key($rateLimit)));
            } catch (InvalidConfigurationException $invalid) {
                self::assertStringContainsString(\sprintf('"tilttek_dice_probability.rate_limit.%s"', key($rateLimit)), $invalid->getMessage());
            }
        }

        self::assertSame([1, 1, 1, 0, 1, 1], $container->getDefinition(Settings::class)->getArguments());
        self::assertSame(1, $container->getDefinition(ProbabilityServices::LIMITS)->getArgument(1));
        self::assertSame(1, $container->getDefinition(DiceStatistics::class)->getArgument(3));
    }

    public function testTheProbabilitiesRollFewerDiceThanARoll(): void
    {
        $limits = ProbabilityServices::limits(new Limits(dice: 80, sides: 20, explosions: 7, expressions: 3, length: 64, rerolls: 9, repetitions: 4, text: 99), 50);

        self::assertEquals(new Limits(dice: 50, sides: 20, explosions: 7, expressions: 3, length: 64, rerolls: 9, repetitions: 4, text: 99), $limits);
        self::assertSame(30, ProbabilityServices::limits(new Limits(dice: 30), 50)->dice);
    }

    public function testTheDiceBundleIsNeeded(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('TilttekDiceProbabilityBundle reads expressions with TilttekDiceBundle, from tilttek/dice-bundle: register it too.');

        $extension->prepend(new ContainerBuilder());
    }

    public function testWithTheDiceBundleNothingIsPrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $dice = new TilttekDiceBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $dice);
        $container->registerExtension($dice);

        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('tilttek_dice'));
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
        $extension = new TilttekDiceProbabilityBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
