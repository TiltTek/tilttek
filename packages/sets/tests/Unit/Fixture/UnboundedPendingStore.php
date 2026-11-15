<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit\Fixture;

use Override;
use Tilttek\Sets\Contract\PendingDeliveries;

/**
 * A completion store of tests that lists every holder with undelivered completions, whatever the limit asked.
 */
final class UnboundedPendingStore extends DelegatingCompletionStore implements PendingDeliveries
{
    #[Override]
    public function holdersWithUndelivered(int $limit): array
    {
        return $this->inner->holdersWithUndelivered(\PHP_INT_MAX);
    }
}
