<?php

declare(strict_types=1);

namespace Tilttek\Badges\Criterion;

use Override;
use Tilttek\Badges\Progress;

/**
 * Awarded by hand, by an administrator: no activity reaches it.
 */
final readonly class Manual implements Criterion
{
    #[Override]
    public function leaves(string $path): array
    {
        return [];
    }

    #[Override]
    public function progress(string $path, Reading $reading): Progress
    {
        return new Progress(0, 1);
    }
}
