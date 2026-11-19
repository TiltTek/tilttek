<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom;

/**
 * A place on the public leaderboard of a room. Teams with the same final time and the same hints share a position.
 */
final readonly class Standing
{
    /**
     * @param int $position from 1
     */
    public function __construct(
        public int $position,
        public RoomResult $result,
    ) {
    }
}
