<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit\Fixture;

use Override;
use RuntimeException;
use Tilttek\Bounties\BountyHold;
use Tilttek\Bounties\Contract\BountyEscrow;

/**
 * An escrow that fails to settle as many times as asked, then passes every move to another.
 */
final class FailingEscrow implements BountyEscrow
{
    public int $failures = 1;

    public function __construct(
        private readonly BountyEscrow $escrow,
    ) {
    }

    #[Override]
    public function reserve(BountyHold $hold): bool
    {
        return $this->escrow->reserve($hold);
    }

    #[Override]
    public function confirm(BountyHold $hold, array $payouts): void
    {
        $this->fail();
        $this->escrow->confirm($hold, $payouts);
    }

    #[Override]
    public function cancel(BountyHold $hold): void
    {
        $this->fail();
        $this->escrow->cancel($hold);
    }

    private function fail(): void
    {
        if ($this->failures > 0) {
            --$this->failures;

            throw new RuntimeException('The ledger is away.');
        }
    }
}
