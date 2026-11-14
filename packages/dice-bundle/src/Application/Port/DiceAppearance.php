<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application\Port;

use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Application\Accessibility;
use Tilttek\DiceBundle\Application\DieAppearance;

/**
 * The extension point that dresses a member's dice: for a member and a number of sides, the appearance of the dice to
 * show — those of a set the member owns, for instance —, or none for the plain ones. The viewer's accessibility, when
 * the page knows it, asks for the dice in high contrast; null leaves the choice to the appearance.
 */
interface DiceAppearance
{
    public function appearance(ResourceReference $member, int $sides, ?Accessibility $viewer = null): ?DieAppearance;
}
