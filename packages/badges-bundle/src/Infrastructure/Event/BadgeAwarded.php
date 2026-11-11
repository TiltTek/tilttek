<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Event;

use Tilttek\Badges\Award;
use Tilttek\Badges\Badge;

/**
 * Dispatched when a member earns a badge through their activity: the application notifies them, or shows it at once.
 * An award made by hand is the application's own act, and dispatches nothing.
 */
final readonly class BadgeAwarded
{
    public function __construct(
        public Award $award,
        public Badge $badge,
    ) {
    }
}
