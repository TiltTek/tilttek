<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application\Port;

use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Application\DieAppearance;

/**
 * The extension point that dresses a member's dice: for a member and a number of sides, the appearance of the dice to
 * show — those of a set the member owns, for instance —, or none for the plain ones.
 */
interface DiceAppearance
{
    public function appearance(ResourceReference $member, int $sides): ?DieAppearance;
}
