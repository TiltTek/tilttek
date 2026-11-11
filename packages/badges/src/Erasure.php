<?php

declare(strict_types=1);

namespace Tilttek\Badges;

/**
 * What erasing a member removed: their awards, the contributions of their activity, their profile — the badges shown,
 * the showcase, the title — and the consents they gave to other applications.
 */
final readonly class Erasure
{
    public function __construct(
        public int $awards,
        public int $contributions,
        public int $grants,
        public bool $profile,
    ) {
    }
}
