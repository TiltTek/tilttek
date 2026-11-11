<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Event;

use Override;

/**
 * No two dice show the same value.
 */
final readonly class AllDifferent implements Event
{
    #[Override]
    public function matches(Faces $faces): bool
    {
        return $faces->mostOfAKind() <= 1;
    }
}
