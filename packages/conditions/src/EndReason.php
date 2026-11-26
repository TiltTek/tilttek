<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

/**
 * Why a condition ended.
 */
enum EndReason: string
{
    /** Removed by the table. */
    case Removed = 'removed';

    /** Its duration ran out. */
    case Expired = 'expired';

    /** Its value fell to zero. */
    case Decayed = 'decayed';

    /** A new application of the same condition took its place. */
    case Replaced = 'replaced';
}
