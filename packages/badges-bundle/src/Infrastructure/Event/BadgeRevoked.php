<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Event;

use Tilttek\Badges\Award;
use Tilttek\Badges\Badge;

/**
 * Dispatched when a revocable badge is withdrawn because the activity that earned it was cancelled.
 */
final readonly class BadgeRevoked
{
    public function __construct(
        public Award $award,
        public Badge $badge,
    ) {
    }
}
