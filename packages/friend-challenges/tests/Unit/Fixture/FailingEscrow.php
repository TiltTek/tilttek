<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit\Fixture;

use Override;
use RuntimeException;
use Tilttek\FriendChallenges\Contract\PrizeEscrow;
use Tilttek\FriendChallenges\PrizeHold;

/**
 * An escrow that fails to settle as many times as asked, then passes every move to another.
 */
final class FailingEscrow implements PrizeEscrow
{
    public int $failures = 1;

    public function __construct(
        private readonly PrizeEscrow $escrow,
    ) {
    }

    #[Override]
    public function reserve(PrizeHold $hold): bool
    {
        return $this->escrow->reserve($hold);
    }

    #[Override]
    public function confirm(PrizeHold $hold, array $payouts): void
    {
        $this->fail();
        $this->escrow->confirm($hold, $payouts);
    }

    #[Override]
    public function cancel(PrizeHold $hold): void
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
