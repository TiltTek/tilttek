<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit\Fixture;

use Closure;
use RuntimeException;

/**
 * The processor time of a piece of work, counted in runs of a fixed reference loop timed by the same process just
 * before and just after it: a busy machine, a slower core or a coverage driver slows the work and the reference
 * alike, so the count stays near what it is on an idle machine, while a computation that does more work than its
 * bounds allow counts many more runs anywhere.
 */
final class ProcessorTime
{
    /** The turns of the reference loop: a fifth of a second of processor time on a recent machine. */
    private const int TURNS = 5_000_000;

    /** The last state of the reference loop, the seed of the next one, so that no optimizer drops the loop. */
    private static int $state = 1;

    /**
     * Runs the work and gives how many runs of the reference loop its processor time is worth; the slower of the two
     * references is taken, the conditions of the work lying between them.
     */
    public static function inReferences(Closure $work): float
    {
        $before = self::reference();
        $start = self::now();
        $work();
        $spent = self::now() - $start;

        return $spent / max($before, self::reference());
    }

    /**
     * The processor time of the reference loop, in seconds: integer arithmetic of the PHP engine, as the arithmetic
     * of the library is.
     */
    private static function reference(): float
    {
        $start = self::now();
        $state = self::$state;

        for ($turn = 0; $turn < self::TURNS; ++$turn) {
            $state = ($state * 1_103_515_245 + 12_345) & 0x7FFF_FFFF;
        }

        self::$state = $state;

        return max(self::now() - $start, 1e-6);
    }

    /**
     * The processor time of this process in user mode, in seconds.
     */
    private static function now(): float
    {
        $usage = getrusage();

        if (!\is_array($usage) || !\is_int($usage['ru_utime.tv_sec'] ?? null) || !\is_int($usage['ru_utime.tv_usec'] ?? null)) {
            throw new RuntimeException('The processor time of the process cannot be read.');
        }

        return $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1_000_000;
    }
}
