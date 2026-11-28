<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Event;

/**
 * A copy was offered for trade: a trade service may read it, as the shelf's visibility allows.
 */
final readonly class CopyOfferedForTrade implements ShelfEvent
{
    public function __construct(public string $member, public string $copy, public string $game)
    {
    }
}
