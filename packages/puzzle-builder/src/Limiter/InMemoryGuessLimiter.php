<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Limiter;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Tilttek\PuzzleBuilder\Contract\GuessLimiter;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;

/**
 * Guesses counted in a fixed window, in the memory of the process: for tests and for a game played in one process.
 * Each key gets `$limit` guesses, then waits for the end of its window of `$seconds`.
 *
 * It keeps its counts between two calls: a web application counts its guesses in a store every worker reads, through
 * `RateLimiterGuessLimiter` or a limiter of its own.
 */
final class InMemoryGuessLimiter implements GuessLimiter
{
    /** The most keys kept: past it, the windows already over are dropped. */
    public const int MAX_KEYS = 10_000;

    /** @var array<string, array{0: int, 1: int}> the start of the window, in seconds, and the guesses in it */
    private array $windows = [];

    /**
     * @throws InvalidScenario when the limit or the period is under 1
     */
    public function __construct(
        private readonly int $limit = 10,
        private readonly int $seconds = 60,
        private readonly ClockInterface $clock = new NativeClock(),
    ) {
        if ($limit < 1 || $seconds < 1) {
            throw InvalidScenario::because('A limit of guesses allows 1 guess or more in 1 second or more.');
        }
    }

    public function attempt(string $key): Allowance
    {
        $now = $this->clock->now();
        [$start, $count] = $this->windows[$key] ?? [$now->getTimestamp(), 0];

        if ($now->getTimestamp() - $start >= $this->seconds) {
            [$start, $count] = [$now->getTimestamp(), 0];
        }

        if ($count >= $this->limit) {
            return Allowance::refused($now->setTimestamp($start + $this->seconds));
        }

        if (!isset($this->windows[$key]) && \count($this->windows) >= self::MAX_KEYS) {
            $this->windows = array_filter($this->windows, fn (array $window): bool => $now->getTimestamp() - $window[0] < $this->seconds);
        }

        $this->windows[$key] = [$start, $count + 1];

        return Allowance::granted();
    }

    /**
     * How many keys the limiter keeps.
     */
    public function keys(): int
    {
        return \count($this->windows);
    }
}
