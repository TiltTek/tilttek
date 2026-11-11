<?php

declare(strict_types=1);

namespace Tilttek\Collections\Daily;

/**
 * Why an item is the item of a day: a rule of the calendar put it there, the weighted draw of a free day did, or the
 * site chose it.
 */
enum PickOrigin: string
{
    case Rule = 'rule';
    case Drawn = 'drawn';
    case Chosen = 'chosen';
}
