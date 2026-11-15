<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Sets\Contract\PendingDeliveries;

/**
 * A completion store of tests that lists every holder with undelivered completions, whatever the limit asked.
 */
final class UnboundedPendingStore extends DelegatingCompletionStore implements PendingDeliveries
{
    #[Override]
    public function holdersWithUndelivered(int $limit, DateTimeImmutable $now): array
    {
        return $this->inner->holdersWithUndelivered(\PHP_INT_MAX, $now);
    }

    #[Override]
    public function failedDeliveries(ResourceReference $holder): int
    {
        return $this->inner->failedDeliveries($holder);
    }

    #[Override]
    public function postpone(ResourceReference $holder, int $failures, DateTimeImmutable $until): void
    {
        $this->inner->postpone($holder, $failures, $until);
    }

    #[Override]
    public function forgetFailures(ResourceReference $holder): void
    {
        $this->inner->forgetFailures($holder);
    }
}
