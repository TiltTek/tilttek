<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Infrastructure\DependencyInjection;

use Phil\RateLimit\RateLimitGuard;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Limits;
use Tilttek\DiceProbability\Probabilities;
use Tilttek\DiceProbability\Settings;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;
use Tilttek\DiceProbabilityBundle\Application\GroupSearch;
use Tilttek\DiceProbabilityBundle\Application\Rounding;
use Tilttek\DiceProbabilityBundle\Infrastructure\RateLimiter\ComputationThrottle;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\Components\DistributionComponent;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\KitComponents;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\ProbabilityExtension;
use Twig\Attribute\AsTwigFunction;

/**
 * The bundle's services: the library's `Settings` and `Probabilities` on the custom dice and the limits of
 * `TilttekDiceBundle` — with fewer dice when `max_dice` says so —, `GroupSearch` on the same limits and samples,
 * `DiceStatistics` on its `DiceRoller`, and — when their components are installed — the names of the UX kit's
 * components, the Twig extension and the Live Component, with its rate limit unless it is turned off.
 *
 * @phpstan-type RateLimit array{enabled: bool, limit: int, interval: string, cache_pool: string, limiter: ?string, single_server: bool}
 * @phpstan-type Config array{explosion_depth: int, work_limit: int, samples: int, seed: int, roll_limit: int, max_dice: int, max_outcomes: int, group_work_limit: int, rounding: string, kit_subdirectory: ?string, rate_limit: RateLimit}
 */
final class ProbabilityServices
{
    /** The limits of a roll, with the dice of the probabilities' own bound. */
    public const string LIMITS = 'tilttek_dice_probability.limits';

    /** The rate limiter factory of the Live Component, when the bundle builds it. */
    public const string LIMITER = 'tilttek_dice_probability.limiter';

    /** The storage of that factory, in the configured cache pool. */
    public const string LIMITER_STORAGE = 'tilttek_dice_probability.limiter.storage';

    private function __construct()
    {
    }

    /**
     * The limits the probabilities check an expression against: those of a roll, with at most `$dice` dice.
     */
    public static function limits(Limits $roll, int $dice): Limits
    {
        return new Limits(min($roll->dice, $dice), $roll->sides, $roll->explosions, $roll->expressions, $roll->length, $roll->rerolls, $roll->repetitions, $roll->text);
    }

    /**
     * @param Config $config
     *
     * @throws InvalidConfigurationException when the rate limit is on without symfony/rate-limiter
     */
    public static function register(ContainerBuilder $container, array $config, bool $rateLimiter = true): void
    {
        $container->register(Settings::class, Settings::class)->setArguments([$config['explosion_depth'], $config['work_limit'], $config['samples'], $config['seed'], $config['roll_limit'], $config['group_work_limit']]);
        $container->register(self::LIMITS, Limits::class)
            ->setFactory([self::class, 'limits'])
            ->setArguments([new Reference(Limits::class), $config['max_dice']]);
        $container->register(Probabilities::class, Probabilities::class)
            ->setArguments([new Reference(CustomDice::class), new Reference(self::LIMITS), new Reference(Settings::class)])
            ->setPublic(true);
        $container->register(GroupSearch::class, GroupSearch::class)
            ->setArguments([new Reference(self::LIMITS), $config['samples'], $config['group_work_limit']]);
        $container->register(DiceStatistics::class, DiceStatistics::class)
            ->setArguments([new Reference(DiceRoller::class), new Reference(Probabilities::class), Rounding::from($config['rounding']), $config['max_outcomes'], new Reference(GroupSearch::class)])
            ->setPublic(true);

        if (class_exists(AsTwigFunction::class)) {
            $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
            $container->register(ProbabilityExtension::class, ProbabilityExtension::class)
                ->setArguments([new Reference(DiceStatistics::class), new Reference(KitComponents::class)])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime')
                ->setPublic(true);
        }

        if (class_exists(AsLiveComponent::class)) {
            $container->register(DistributionComponent::class, DistributionComponent::class)
                ->setArguments([new Reference(DiceStatistics::class), self::throttle($container, $config['rate_limit'], $rateLimiter)])
                ->setAutoconfigured(true);
        }
    }

    /**
     * The rate limit of the Live Component: none when it is turned off; otherwise a sliding window in the configured
     * cache pool, or the factory the configuration names. The pool is noted for `RateLimitGuard`, which refuses one
     * local to each server in the `prod` environment unless `single_server` says the application runs on one.
     *
     * @param RateLimit $rateLimit
     * @param bool      $installed whether symfony/rate-limiter is there
     *
     * @throws InvalidConfigurationException when the rate limit is on without symfony/rate-limiter
     */
    private static function throttle(ContainerBuilder $container, array $rateLimit, bool $installed): ?Reference
    {
        if (!$rateLimit['enabled']) {
            return null;
        }

        RateLimitGuard::requireComponent($installed, 'tilttek_dice_probability.rate_limit');

        $factory = $rateLimit['limiter'];

        if (null === $factory) {
            $factory = self::LIMITER;
            RateLimitGuard::watch($container, $rateLimit['cache_pool'], 'tilttek_dice_probability.rate_limit.cache_pool', $rateLimit['single_server'], 'enabled');
            $container->register(self::LIMITER_STORAGE, CacheStorage::class)->setArguments([new Reference($rateLimit['cache_pool'])]);
            $container->register(self::LIMITER, RateLimiterFactory::class)->setArguments([
                ['id' => 'tilttek_dice_probability', 'policy' => 'sliding_window', 'limit' => $rateLimit['limit'], 'interval' => $rateLimit['interval']],
                new Reference(self::LIMITER_STORAGE),
            ]);
        }

        $container->register(ComputationThrottle::class, ComputationThrottle::class)
            ->setArguments([new Reference($factory), new Reference('request_stack'), '%kernel.secret%']);

        return new Reference(ComputationThrottle::class);
    }
}
