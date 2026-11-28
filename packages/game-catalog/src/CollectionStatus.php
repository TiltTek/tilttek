<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * Where a game stands in a member's collection; a game may hold several.
 */
enum CollectionStatus: string
{
    case Owned = 'owned';
    case PreviouslyOwned = 'previously_owned';
    case Preordered = 'preordered';
    case ForTrade = 'for_trade';
    case WantInTrade = 'want_in_trade';
    case WantToBuy = 'want_to_buy';
    case WantToPlay = 'want_to_play';
    case Wishlist = 'wishlist';
}
