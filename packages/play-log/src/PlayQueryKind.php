<?php

declare(strict_types=1);

namespace Tilttek\PlayLog;

/**
 * Whose plays a query reads.
 */
enum PlayQueryKind: string
{
    /** The plays where the member holds a confirmed seat. */
    case Member = 'member';

    /** The plays where the member holds a seat not confirmed yet. */
    case Pending = 'pending';

    /** The plays the member recorded. */
    case RecordedBy = 'recorded-by';

    /** The plays of the group. */
    case Group = 'group';
}
