<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Event;

/**
 * A copy was taken off the shelf.
 */
final readonly class CopyRemoved implements ShelfEvent
{
    public function __construct(public string $member, public string $copy, public string $game)
    {
    }
}
