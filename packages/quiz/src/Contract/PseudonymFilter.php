<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Contract;

/**
 * Whether a guest may play under a pseudonym: the application's word list, a moderation service or a reserved-name
 * check. Without one, every pseudonym the shape rules of `Player` accept is allowed.
 */
interface PseudonymFilter
{
    public function allows(string $pseudonym): bool;
}
