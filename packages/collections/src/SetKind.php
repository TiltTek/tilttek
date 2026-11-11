<?php

declare(strict_types=1);

namespace Tilttek\Collections;

/**
 * How a set comes to a member: item by item (`plain`), given to every member on arrival (`base`), obtained for the
 * site's points through its exchange catalog (`earned`), or bought for oneself or as a gift (`paid`). An earned or
 * paid set is given whole when the application confirms the exchange or the purchase.
 */
enum SetKind: string
{
    case Plain = 'plain';
    case Base = 'base';
    case Earned = 'earned';
    case Paid = 'paid';
}
