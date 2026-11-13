<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Exception\InvalidArmyList;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;

/**
 * What a refused value belongs to, and so the exception that refuses it.
 *
 * @internal
 */
enum Fault
{
    case Catalogue;
    case ArmyList;

    public function because(string $reason): InvalidArmyList|InvalidCatalogue
    {
        return match ($this) {
            self::Catalogue => InvalidCatalogue::because($reason),
            self::ArmyList => InvalidArmyList::because($reason),
        };
    }
}
