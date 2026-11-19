<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * The hint screen cleared by the host: back to the countdown alone. It names identifiers only.
 */
final readonly class ScreenCleared
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
