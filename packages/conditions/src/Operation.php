<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

/**
 * What a modifier does to a statistic. The operations run in the order of the cases: every multiplication, then every
 * addition, then the floors, then the caps, then the override.
 */
enum Operation: string
{
    /** Multiplies by the amount in percent: 50 halves, 200 doubles. */
    case Multiply = 'multiply';

    /** Adds the amount, below zero to take away. */
    case Add = 'add';

    /** Raises to the amount at least. */
    case Upgrade = 'upgrade';

    /** Lowers to the amount at most. */
    case Downgrade = 'downgrade';

    /** Sets to the amount. */
    case Override = 'override';
}
