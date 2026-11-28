<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

/**
 * Why an act on an inventory is refused.
 */
enum Refusal: string
{
    case AlreadyOpen = 'already_open';

    case TooManyInventories = 'too_many_inventories';

    case ItemExists = 'item_exists';

    case TooManyItems = 'too_many_items';

    case NotEnough = 'not_enough';

    case NotAContainer = 'not_a_container';

    case ContainerCycle = 'container_cycle';

    case TooDeep = 'too_deep';

    case ContainerFull = 'container_full';

    case ContainerNotEmpty = 'container_not_empty';

    case SingleContainer = 'single_container';

    case NotCarried = 'not_carried';

    case OverCapacity = 'over_capacity';
}
