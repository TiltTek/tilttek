<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Contract;

use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Costing;
use Tilttek\WargameArmy\Violation;

/**
 * A rule an army list must keep beyond its units' own bounds: a catalogue declares those of the library, an
 * application adds its own to the validator. A constraint keeps nothing between two calls.
 */
interface Constraint
{
    /**
     * The violations of the rule in the list, each with a message people read; none when the list keeps it.
     *
     * @return list<Violation>
     */
    public function check(ArmyList $list, Catalogue $catalogue, Costing $costing): array;
}
