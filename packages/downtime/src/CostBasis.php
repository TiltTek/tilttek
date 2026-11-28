<?php

declare(strict_types=1);

namespace Tilttek\Downtime;

/**
 * Whether a cost is paid once for the activity, or for each of its days.
 */
enum CostBasis: string
{
    case Once = 'once';
    case PerDay = 'per_day';
}
