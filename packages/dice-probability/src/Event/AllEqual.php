<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Event;

use Override;

/**
 * Every die shows the same value.
 */
final readonly class AllEqual implements Event
{
    #[Override]
    public function matches(Faces $faces): bool
    {
        return \count($faces->counts) <= 1;
    }
}
