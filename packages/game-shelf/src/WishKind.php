<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

use Tilttek\GameCatalog\CollectionStatus;

/**
 * What a member wishes of a game; a wish may hold several.
 */
enum WishKind: string
{
    case Wishlist = 'wishlist';
    case WantToBuy = 'want_to_buy';
    case WantInTrade = 'want_in_trade';
    case WantToPlay = 'want_to_play';

    /**
     * The kind of wish a collection status stands for, or `null` when it stands for a copy.
     */
    public static function fromCollection(CollectionStatus $status): ?self
    {
        return match ($status) {
            CollectionStatus::Wishlist => self::Wishlist,
            CollectionStatus::WantToBuy => self::WantToBuy,
            CollectionStatus::WantInTrade => self::WantInTrade,
            CollectionStatus::WantToPlay => self::WantToPlay,
            default => null,
        };
    }
}
