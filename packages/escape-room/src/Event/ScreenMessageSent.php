<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A message the host types on the console for the hint screen: "Look behind the clock". It is the one event
 * that carries a text, written by the host and never by the players; a bundle keeps it and sends its identifier.
 */
final readonly class ScreenMessageSent
{
    public function __construct(
        public string $room,
        public ResourceReference $team,
        public ?string $session,
        public string $message,
        public ResourceReference $host,
        public DateTimeImmutable $at,
    ) {
    }
}
