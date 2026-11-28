<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Event;

/**
 * A game joined the wishlist: a store may tell the member when it is in stock, if the member agreed to it.
 */
final readonly class GameWished implements ShelfEvent
{
    public function __construct(public string $member, public string $game)
    {
    }
}
