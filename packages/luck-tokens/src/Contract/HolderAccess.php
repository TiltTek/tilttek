<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Contract;

use Phil\Resource\ResourceReference;

/**
 * Whether an actor spends or passes the tokens of a holder: a player those of the character they play, for instance.
 * The game masters of the table always may.
 */
interface HolderAccess
{
    public function controls(ResourceReference $actor, ResourceReference $holder): bool;
}
