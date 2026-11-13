<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Limiter;

use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Tilttek\PuzzleBuilder\Contract\GuessLimiter;

/**
 * The guesses counted by a limiter of `symfony/rate-limiter`: its policy — a fixed window, a sliding window, a token
 * bucket — and its storage are the factory's.
 *
 * `symfony/rate-limiter` is suggested, not required: an application that counts the guesses another way leaves it out.
 */
final readonly class RateLimiterGuessLimiter implements GuessLimiter
{
    public function __construct(
        private RateLimiterFactoryInterface $factory,
    ) {
    }

    public function attempt(string $key): Allowance
    {
        $limit = $this->factory->create($key)->consume();

        return $limit->isAccepted() ? Allowance::granted() : Allowance::refused($limit->getRetryAfter());
    }
}
