<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

use Tilttek\GameCatalog\CollectionStatus;

/**
 * Where a copy stands on its member's shelf; a copy may hold several.
 */
enum CopyStatus: string
{
    case Owned = 'owned';
    case PreviouslyOwned = 'previously_owned';
    case Preordered = 'preordered';
    case ForTrade = 'for_trade';

    /**
     * The status of a copy a collection status stands for, or `null` when it stands for a wish.
     */
    public static function fromCollection(CollectionStatus $status): ?self
    {
        return match ($status) {
            CollectionStatus::Owned => self::Owned,
            CollectionStatus::PreviouslyOwned => self::PreviouslyOwned,
            CollectionStatus::Preordered => self::Preordered,
            CollectionStatus::ForTrade => self::ForTrade,
            default => null,
        };
    }
}
