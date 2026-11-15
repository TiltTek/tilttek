<?php

declare(strict_types=1);

namespace Tilttek\TableSafety;

use Tilttek\TableSafety\Exception\InvalidAnswer;

/**
 * The threshold of the summaries shown to people — the pact, the shown agreement, the stars and wishes —: they hold
 * answers only in whole groups of this many participants, taken in the order they were given. Under the threshold a
 * summary holds none; past it, the answers of a group that is not full wait for the group to fill, so that one more
 * answer never changes a summary by the answers of one person alone.
 */
final class RespondentThreshold
{
    /** How many participants' answers a summary waits for, unless the application chooses otherwise. */
    public const int DEFAULT = 3;

    private function __construct()
    {
    }

    /**
     * @phpstan-assert positive-int $threshold
     *
     * @throws InvalidAnswer when the threshold is under 1
     */
    public static function assert(int $threshold): void
    {
        if ($threshold < 1) {
            throw InvalidAnswer::because(\sprintf('A summary waits for at least 1 answer, not %d.', $threshold));
        }
    }

    /**
     * How many of the answers a summary holds: the largest multiple of the threshold that is not over them.
     *
     * @param positive-int $threshold
     */
    public static function counted(int $answered, int $threshold): int
    {
        return intdiv($answered, $threshold) * $threshold;
    }
}
