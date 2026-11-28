<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

/**
 * The eight phases of a moon, from new to new, each covering an eighth of its cycle around its exact moment.
 */
enum Phase: string
{
    case New = 'new';
    case WaxingCrescent = 'waxing-crescent';
    case FirstQuarter = 'first-quarter';
    case WaxingGibbous = 'waxing-gibbous';
    case Full = 'full';
    case WaningGibbous = 'waning-gibbous';
    case LastQuarter = 'last-quarter';
    case WaningCrescent = 'waning-crescent';
}
