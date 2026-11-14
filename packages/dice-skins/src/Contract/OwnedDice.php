<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\DiceSkins\OwnedDie;

/**
 * The dice of the sets a member owns, read from the collection that holds them.
 */
interface OwnedDice
{
    /**
     * Every die the member owns.
     *
     * @return list<OwnedDie>
     */
    public function ownedBy(ResourceReference $member): array;

    /**
     * The die the member chose to roll for a number of sides, if they chose one they still own.
     */
    public function chosen(ResourceReference $member, int $sides): ?OwnedDie;
}
