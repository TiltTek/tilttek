<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Event;

/**
 * A copy offered for trade no longer is, or left the shelf.
 */
final readonly class CopyWithdrawnFromTrade implements ShelfEvent
{
    public function __construct(public string $member, public string $copy, public string $game)
    {
    }
}
