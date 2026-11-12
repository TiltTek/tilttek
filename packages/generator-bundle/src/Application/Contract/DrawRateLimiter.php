<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application\Contract;

/**
 * A rate limit on the draws of the API, counted per key: a client, or the address of an anonymous caller.
 */
interface DrawRateLimiter
{
    /**
     * Counts one draw; null when it is accepted, otherwise the seconds to wait before the next one.
     */
    public function consume(string $key): ?int;
}
