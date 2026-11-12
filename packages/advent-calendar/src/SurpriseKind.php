<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

/**
 * What a surprise refers to; the application grants it.
 */
enum SurpriseKind: string
{
    /** An amount of points, credited to the ledger the reference names. */
    case Points = 'points';

    /** A badge, by its identifier. */
    case Badge = 'badge';

    /** A piece of content revealed — a page, a download, an image —, by its resource reference. */
    case Content = 'content';

    /** A code handed out — a discount, an access —, by the pool the application draws it from. */
    case Code = 'code';
}
