<?php

declare(strict_types=1);

namespace Tilttek\Generator\Engine;

use Random\Randomizer;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Timer;

/**
 * The making of one result: its randomness, the picks and steps it has made, the memory it has grown by, the time it
 * and the whole draw have taken, and the generators it is inside, each with its language — the default one outside
 * them.
 *
 * @internal
 */
final class Run
{
    private int $picks = 0;

    private int $steps = 0;

    private readonly int $memory;

    /**
     * @var list<string>
     */
    private array $locales = [];

    private readonly float $started;

    private readonly float $drawStarted;

    /**
     * @param ?float $drawStarted when the draw this result belongs to started; when this result starts if null
     */
    public function __construct(
        public readonly Randomizer $randomizer,
        private readonly Limits $limits,
        private readonly Timer $timer,
        private readonly string $locale,
        ?float $drawStarted = null,
    ) {
        $this->started = $timer->now();
        $this->drawStarted = $drawStarted ?? $this->started;
        $this->memory = memory_get_usage();
    }

    /**
     * Counts one step — a turn of a loop, an assignment — and stops the result past the limits of steps, memory or
     * time: a loop that never picks, or a text that doubles at each assignment, stops at the step that crosses them.
     */
    public function step(): void
    {
        if (++$this->steps > $this->limits->steps) {
            throw LimitExceeded::of('steps per result', $this->limits->steps);
        }

        if (memory_get_usage() - $this->memory > $this->limits->megabytes * 1_048_576) {
            throw LimitExceeded::of('megabytes per result', $this->limits->megabytes);
        }

        $this->checkTime();
    }

    /**
     * Counts one pick, roll or call, and stops the result past the limits of picks or time.
     */
    public function tick(): void
    {
        if (++$this->picks > $this->limits->picks) {
            throw LimitExceeded::of('picks per result', $this->limits->picks);
        }

        $this->checkTime();
    }

    /**
     * Stops the result past the time of one result, or of the whole draw: before each template is rendered, and at
     * each pick.
     */
    public function checkTime(): void
    {
        $now = $this->timer->now();

        if ($now - $this->started > $this->limits->milliseconds) {
            throw LimitExceeded::of('milliseconds per result', $this->limits->milliseconds);
        }

        if ($now - $this->drawStarted > $this->limits->drawMilliseconds) {
            throw LimitExceeded::of('milliseconds per draw', $this->limits->drawMilliseconds);
        }
    }

    public function enter(string $locale): void
    {
        if (\count($this->locales) >= $this->limits->depth) {
            throw LimitExceeded::of('generators called inside each other', $this->limits->depth);
        }

        $this->tick();
        $this->locales[] = $locale;
    }

    public function leave(): void
    {
        array_pop($this->locales);
    }

    public function locale(): string
    {
        return array_last($this->locales) ?? $this->locale;
    }
}
