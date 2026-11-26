<?php

declare(strict_types=1);

namespace Tilttek\Kudos;

/**
 * Why thanks cannot be given.
 */
enum Refusal: string
{
    /**
     * The giver and the receiver are the same member.
     */
    case Self = 'self';

    /**
     * The receiver does not take thanks, from anyone or from this giver.
     */
    case Declined = 'declined';

    /**
     * The giver has given all the thanks of their day, counted in their time zone.
     */
    case DailyCap = 'daily_cap';

    /**
     * The giver has thanked this receiver as many times as the period allows.
     */
    case PairLimit = 'pair_limit';
}
