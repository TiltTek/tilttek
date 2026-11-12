<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Infrastructure\RateLimit;

use Override;
use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Tilttek\GeneratorBundle\Application\Contract\DrawRateLimiter;

/**
 * The rate limit of a Symfony rate limiter the application configures under `framework.rate_limiter`.
 */
final readonly class SymfonyDrawRateLimiter implements DrawRateLimiter
{
    public function __construct(private RateLimiterFactoryInterface $factory, private ClockInterface $clock)
    {
    }

    #[Override]
    public function consume(string $key): ?int
    {
        $limit = $this->factory->create($key)->consume();

        if ($limit->isAccepted()) {
            return null;
        }

        return max(1, $limit->getRetryAfter()->getTimestamp() - $this->clock->now()->getTimestamp());
    }
}
