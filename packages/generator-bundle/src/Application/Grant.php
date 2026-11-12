<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application;

use Tilttek\GeneratorBundle\Application\Contract\DrawRateLimiter;

/**
 * A draw the API allows: who asks — a client, or an anonymous caller by address — and the rate limit it counts against.
 */
final readonly class Grant
{
    public function __construct(public ?string $client, public string $key, private ?DrawRateLimiter $limiter = null)
    {
    }

    /**
     * Counts the draw against the rate limit; null when it is accepted, otherwise the seconds to wait.
     */
    public function retryAfter(): ?int
    {
        return $this->limiter?->consume($this->key);
    }
}
