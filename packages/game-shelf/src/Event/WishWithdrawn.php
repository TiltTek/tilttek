<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Event;

/**
 * A game left the wishlist.
 */
final readonly class WishWithdrawn implements ShelfEvent
{
    public function __construct(public string $member, public string $game)
    {
    }
}
