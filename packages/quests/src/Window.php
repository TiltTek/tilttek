<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use DateTimeImmutable;

/**
 * A stretch of time, from `from`, included, to `until`, excluded; an open end has no bound, and a window that ends
 * before it starts holds no moment.
 */
final readonly class Window
{
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $until = null,
    ) {
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        return (!$this->from instanceof DateTimeImmutable || $moment >= $this->from)
            && (!$this->until instanceof DateTimeImmutable || $moment < $this->until);
    }

    /**
     * The time both windows share, which may be empty.
     */
    public function intersect(self $other): self
    {
        return new self($this->later($this->from, $other->from), $this->earlier($this->until, $other->until));
    }

    /**
     * The later of two starts; null, an open start, is before every moment.
     */
    private function later(?DateTimeImmutable $a, ?DateTimeImmutable $b): ?DateTimeImmutable
    {
        return max($a, $b);
    }

    private function earlier(?DateTimeImmutable $a, ?DateTimeImmutable $b): ?DateTimeImmutable
    {
        $bounds = array_filter([$a, $b]);

        return [] === $bounds ? null : min($bounds);
    }
}
