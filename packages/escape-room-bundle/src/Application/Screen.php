<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * What the hint screen of a game shows, until the host shows something else: a hint pushed, by its puzzle and tier, a
 * message, or nothing.
 */
final readonly class Screen
{
    /**
     * @param ?int    $tier    from 1, for a hint
     * @param int     $penalty the seconds a hint adds to the final time
     * @param ?string $message the text the host typed, for a message
     */
    public function __construct(
        public ScreenKind $kind,
        public ResourceReference $host,
        public DateTimeImmutable $at,
        public ?string $puzzle = null,
        public ?int $tier = null,
        public int $penalty = 0,
        public ?string $message = null,
    ) {
    }
}
