<?php

declare(strict_types=1);

namespace Tilttek\Badges;

use Tilttek\Badges\Exception\InvalidBadge;

/**
 * How far a member is towards a badge: *7 of 10*. The current value may pass the target — 23 comments for a badge of
 * 10 —, which a repeatable badge counts as two awards.
 */
final readonly class Progress
{
    public function __construct(
        public int $current,
        public int $target,
    ) {
        if ($current < 0) {
            throw InvalidBadge::because(\sprintf('A progress is never negative; %d is.', $current));
        }

        if ($target < 1) {
            throw InvalidBadge::because(\sprintf('The target of a progress is at least 1; %d is not.', $target));
        }
    }

    public function isReached(): bool
    {
        return $this->current >= $this->target;
    }

    /**
     * The current value, at most the target: what *7 of 10* shows.
     */
    public function shown(): int
    {
        return min($this->current, $this->target);
    }

    /**
     * How many times the target is reached: once at most, unless the badge is repeatable.
     */
    public function times(bool $repeatable): int
    {
        $times = intdiv($this->current, $this->target);

        return $repeatable ? $times : min($times, 1);
    }
}
