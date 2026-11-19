<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A game resumed by the host: the countdown runs again. It names identifiers only.
 */
final readonly class GameResumed
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
