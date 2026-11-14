<?php

declare(strict_types=1);

namespace Tilttek\Rating;

/**
 * How a rated result moved one member's score, in hundredths, and whether it is the highest the member reached.
 */
final readonly class RatingChange
{
    public function __construct(
        public string $member,
        public int $before,
        public int $after,
        public bool $peak,
    ) {
    }

    /**
     * The change in hundredths: above 0 for a gain.
     */
    public function delta(): int
    {
        return $this->after - $this->before;
    }
}
