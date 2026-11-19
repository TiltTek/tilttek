<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle;

use LogicException;
use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;
use Tilttek\DiceProbabilityBundle\Application\GroupSearch;
use Tilttek\DiceProbabilityBundle\Application\Rounding;
use Tilttek\DiceProbabilityBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Tilttek\DiceProbabilityBundle\Infrastructure\DependencyInjection\ProbabilityServices;

/**
 * The probabilities of dice expressions in a Symfony application, on `tilttek/dice-probability`:
 *
 * ```yaml
 * tilttek_dice_probability:
 *     explosion_depth: 10
 *     work_limit: 100000
 *     samples: 5000
 *     seed: 0
 *     roll_limit: 50000
 *     max_dice: 50
 *     max_outcomes: 500
 *     group_work_limit: 2000000
 *     rounding: floor
 *     kit_subdirectory: ~
 *     rate_limit:
 *         enabled: true
 *         limit: 60
 *         interval: '1 minute'
 *         cache_pool: cache.app
 *         limiter: ~
 * ```
 *
 * The expressions are read by the `DiceRoller` of `TilttekDiceBundle`, with its notations, limits and custom dice:
 * register it too. The configuration sets how far an exact computation goes, how an estimate is drawn, how much work a
 * page may ask for, how a division rounds when the expression does not say, and where the recipes of the UX kit the
 * templates draw with are installed. The defaults keep a page that anyone may type into within a fraction of a second,
 * and the Live Component computes at most 60 expressions a minute for each client, by its IPv4 address or its IPv6 /64:
 * with the Live Component installed, that rate limit needs `symfony/rate-limiter`, or `enabled: false`.
 */
final class TilttekDiceProbabilityBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'tilttek_dice_probability';

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
                ->integerNode('explosion_depth')
                    ->info('How many explosions per die an exact computation follows; the probability of more is stated.')
                    ->min(1)
                    ->defaultValue(10)
                ->end()
                ->integerNode('work_limit')
                    ->info('How much work an exact computation does at most before an estimate takes over.')
                    ->min(1)
                    ->defaultValue(100_000)
                ->end()
                ->integerNode('samples')
                    ->info('How many rolls an estimate is drawn from.')
                    ->min(1)
                    ->defaultValue(5_000)
                ->end()
                ->integerNode('seed')
                    ->info('The seed of the rolls of an estimate: the same seed gives the same estimate.')
                    ->defaultValue(0)
                ->end()
                ->integerNode('roll_limit')
                    ->info('How many dice an estimate rolls at most over all its samples; past it, the expression has no probabilities.')
                    ->min(1)
                    ->defaultValue(50_000)
                ->end()
                ->integerNode('max_dice')
                    ->info('How many dice an expression rolls at most for its probabilities, below the limit of a roll.')
                    ->min(1)
                    ->defaultValue(50)
                ->end()
                ->integerNode('max_outcomes')
                    ->info('How many values a distribution has at most; past it, it is refused rather than drawn.')
                    ->min(1)
                    ->defaultValue(DiceStatistics::MAX_OUTCOMES)
                ->end()
                ->integerNode('group_work_limit')
                    ->info('How many steps the search of the groups of an estimate takes at most over all its samples, "6d6g10"; past it, the expression is refused before it is rolled.')
                    ->min(1)
                    ->defaultValue(GroupSearch::WORK_LIMIT)
                ->end()
                ->enumNode('rounding')
                    ->info('How a division rounds when the expression does not say: floor, ceil, round, towards_zero or away_from_zero.')
                    ->values(array_map(static fn (Rounding $rounding): string => $rounding->value, Rounding::cases()))
                    ->defaultValue(Rounding::Floor->value)
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the UX kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
                ->arrayNode('rate_limit')
                    ->info('The computations the Live Component does for each client, by its IPv4 address or its IPv6 /64, with symfony/rate-limiter.')
                    ->canBeDisabled()
                    ->children()
                        ->integerNode('limit')->info('The computations of one client within the interval.')->min(1)->defaultValue(60)->end()
                        ->scalarNode('interval')->info('The sliding window the limit counts over, as symfony/rate-limiter reads it.')->cannotBeEmpty()->defaultValue('1 minute')->end()
                        ->scalarNode('cache_pool')->info('The cache pool the counts are kept in: one shared by the servers counts a client once.')->cannotBeEmpty()->defaultValue('cache.app')->end()
                        ->scalarNode('limiter')->info('The service id of a rate limiter factory of the application, instead of the one built here.')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('tilttek_dice')) {
            throw new LogicException('TilttekDiceProbabilityBundle reads expressions with TilttekDiceBundle, from tilttek/dice-bundle: register it too.');
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{explosion_depth: int, work_limit: int, samples: int, seed: int, roll_limit: int, max_dice: int, max_outcomes: int, group_work_limit: int, rounding: string, kit_subdirectory: ?string, rate_limit: array{enabled: bool, limit: int, interval: string, cache_pool: string, limiter: ?string}} $config */
        ProbabilityServices::register($builder, $config, interface_exists(RateLimiterFactoryInterface::class));
    }
}
