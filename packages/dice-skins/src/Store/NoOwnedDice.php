<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\DiceSkins\Contract\OwnedDice;
use Tilttek\DiceSkins\OwnedDie;

/**
 * The owned dice of a site without collections: nobody owns any.
 */
final readonly class NoOwnedDice implements OwnedDice
{
    #[Override]
    public function ownedBy(ResourceReference $member): array
    {
        return [];
    }

    #[Override]
    public function chosen(ResourceReference $member, int $sides): ?OwnedDie
    {
        return null;
    }
}
