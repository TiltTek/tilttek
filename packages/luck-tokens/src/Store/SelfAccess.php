<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\LuckTokens\Contract\HolderAccess;

/**
 * The access of tokens held by players: each player controls their own.
 */
final readonly class SelfAccess implements HolderAccess
{
    #[Override]
    public function controls(ResourceReference $actor, ResourceReference $holder): bool
    {
        return $actor->equals($holder);
    }
}
