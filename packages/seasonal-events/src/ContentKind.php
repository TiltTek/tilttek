<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

/**
 * What a season or an event brings while it lasts; the application, or the package it belongs to, knows what each
 * reference stands for.
 */
enum ContentKind: string
{
    /** A quest open while the season or the event lasts. */
    case Quest = 'quest';

    /** An item offered while it lasts: in a shop, a collection, a reward. */
    case Item = 'item';

    /** A decoration of the site while it lasts: a theme, a banner, an illustration. */
    case Decoration = 'decoration';
}
