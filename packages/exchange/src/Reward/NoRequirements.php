<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reward;

use Override;
use Phil\Resource\ResourceReference;

/**
 * The answer when the application checks no condition: no member meets one, so a reward with conditions is had by
 * no one until the application gives its `Requirements`.
 */
final readonly class NoRequirements implements Requirements
{
    #[Override]
    public function meets(ResourceReference $member, string $requirement): bool
    {
        return false;
    }
}
