<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

/**
 * What happened to an inventory or one of its items.
 */
enum Change: string
{
    case Opened = 'opened';

    case Updated = 'updated';

    case Closed = 'closed';

    case Added = 'added';

    case Increased = 'increased';

    case Decreased = 'decreased';

    case Removed = 'removed';

    case Moved = 'moved';

    case Equipped = 'equipped';

    case Unequipped = 'unequipped';

    case TransferredOut = 'transferred_out';

    case TransferredIn = 'transferred_in';

    case Forgotten = 'forgotten';
}
