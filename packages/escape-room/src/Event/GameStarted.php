<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A game started: the countdown runs from `$at`. It names identifiers only.
 */
final readonly class GameStarted
{
    public function __construct(
        public string $room,
        public ResourceReference $team,
        public ?string $session,
        public DateTimeImmutable $at,
    ) {
    }
}
