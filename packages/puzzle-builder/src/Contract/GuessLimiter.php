<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Contract;

use Tilttek\PuzzleBuilder\Limiter\Allowance;

/**
 * How many guesses a team makes on a puzzle in a period: the bound that keeps a script from trying every word.
 *
 * Each call counts one attempt under the key, which names the scenario, the team and the puzzle. The limiter keeps its
 * counts where every worker of the application reads them — a cache, a database —, never in the process alone.
 * `Limiter\RateLimiterGuessLimiter` counts them with `symfony/rate-limiter`.
 */
interface GuessLimiter
{
    public function attempt(string $key): Allowance;
}
