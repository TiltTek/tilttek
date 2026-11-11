<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Functional\App;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Application\DieAppearance;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;

/**
 * The application's dice sets: the member `member:1` rolls green glass d20.
 */
final readonly class MemberDice implements DiceAppearance
{
    #[Override]
    public function appearance(ResourceReference $member, int $sides): ?DieAppearance
    {
        return 'member:1' === $member->toString() && 20 === $sides ? new DieAppearance('Moss', '#2e8b57', 'glass') : null;
    }
}
