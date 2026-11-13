<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Constraint;

/**
 * Where a count applies: across the whole army, or in each detachment on its own.
 */
enum Scope: string
{
    case Army = 'army';
    case Detachment = 'detachment';
}
