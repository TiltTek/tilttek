<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\DependencyInjection;

use LogicException;
use Override;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The default rate limit of the public draws needs the rate limiter builder of `symfony/rate-limiter`: without it, the
 * container is refused, with what to do, rather than an API open to anyone without a limit.
 */
final class PublicRateLimiterPass implements CompilerPassInterface
{
    /** The rate limiter builder of the RateLimiterBundle, which makes the default limiter of the public draws. */
    public const string BUILDER = 'limiter_builder';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasDefinition(GeneratorServices::PUBLIC_LIMITER) && !$container->has(self::BUILDER)) {
            throw new LogicException('The public draws of the API are rate limited by default: install symfony/rate-limiter and enable "framework.rate_limiter", or name a limiter in "tilttek_generator.api.rate_limiter".');
        }
    }
}
