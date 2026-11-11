<?php

declare(strict_types=1);

namespace Tilttek\Progression\Season;

/**
 * The calendar periods seasons follow one another by, and how their codes are written: `2026-11`, `2026-q4`, `2026`.
 */
enum SeasonLength: string
{
    case Month = 'month';

    case Quarter = 'quarter';

    case Year = 'year';
}
