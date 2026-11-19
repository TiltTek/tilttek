<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A game paused by the host: the countdown stops, and the hint screen says so. It names identifiers only.
 */
final readonly class GamePaused
{
    public function __construct(
        public string $room,
        public ResourceReference $team,
        public ?string $session,
        public ResourceReference $host,
        public DateTimeImmutable $at,
    ) {
    }
}
