<?php

declare(strict_types=1);

namespace Tilttek\Badges;

/**
 * Where a member stands with one badge: its progress and how many times they earned it.
 */
final readonly class Standing
{
    public function __construct(
        public Badge $badge,
        public Progress $progress,
        public int $awards,
    ) {
    }

    public function isEarned(): bool
    {
        return $this->awards > 0;
    }
}
