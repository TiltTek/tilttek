<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Event;

use Phil\Resource\ResourceReference;

/**
 * A team that agreed, or no longer agrees, to show its result on the public leaderboard. It names identifiers only.
 */
final readonly class ConsentChanged
{
    public function __construct(
        public string $room,
        public ResourceReference $team,
        public ?string $session,
        public bool $public,
    ) {
    }
}
