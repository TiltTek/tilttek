<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Infrastructure\Security;

use Phil\Resource\ResourceReference;

/**
 * The subject the voters decide on: whether the actor spends or passes the tokens of the holder — a player those of
 * the character they play, for instance.
 */
final readonly class HolderControl
{
    public function __construct(
        public ResourceReference $actor,
        public ResourceReference $holder,
    ) {
    }
}
