<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

/**
 * What applying a condition a character already has does.
 */
enum Stacking: string
{
    /** The applications in place end, and the new one starts. */
    case Replace = 'replace';

    /** The one in place takes the new value, duration and source when the new value is as high or higher; otherwise nothing changes. */
    case Highest = 'highest';

    /** The values add up to the condition's highest value, and the one in place takes the new duration and source. */
    case Sum = 'sum';

    /** Each application is its own, up to the condition's most instances: doses of a poison, wounds. */
    case Separate = 'separate';

    /** The one in place stays as it is. */
    case Ignore = 'ignore';
}
