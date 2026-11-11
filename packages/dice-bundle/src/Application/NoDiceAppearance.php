<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;

/**
 * Plain dice for everyone, until the application plugs an appearance in.
 */
final readonly class NoDiceAppearance implements DiceAppearance
{
    #[Override]
    public function appearance(ResourceReference $member, int $sides): ?DieAppearance
    {
        return null;
    }
}
