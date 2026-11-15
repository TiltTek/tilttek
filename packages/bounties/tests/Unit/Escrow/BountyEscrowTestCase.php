<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit\Escrow;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Bounties\BountyHold;
use Tilttek\Bounties\Contract\BountyEscrow;
use Tilttek\Bounties\Exception\EscrowConflict;
use Tilttek\Bounties\Payout;
use Tilttek\Bounties\Tests\Unit\Fixture\World;
use Tilttek\Points\Exception\InsufficientPoints;

/**
 * The contract every escrow keeps, on Ana holding 100 points and 7 gems: an implementation extends this case.
 */
abstract class BountyEscrowTestCase extends TestCase
{
    public function testAHoldTakesThePointsOnce(): void
    {
        $escrow = $this->escrow();

        self::assertTrue($escrow->reserve(self::hold('a', 30)));
        self::assertFalse($escrow->reserve(self::hold('a', 30)));
        self::assertSame(70, $this->balance(World::ana()));
        self::assertSame(7, $this->balance(World::ana(), 'gems'));
    }

    public function testAHoldThePointsDoNotCoverHoldsNothing(): void
    {
        $escrow = $this->escrow();
        $escrow->reserve(self::hold('a', 80));

        try {
            $escrow->reserve(self::hold('b', 21));
            self::fail('Ana holds 20 points.');
        } catch (InsufficientPoints) {
            self::assertSame(20, $this->balance(World::ana()));
        }

        self::assertTrue($escrow->reserve(self::hold('b', 20)));
        self::assertSame(0, $this->balance(World::ana()));
    }

    public function testAConfirmedHoldPaysTheWinnersAndGivesTheRestBack(): void
    {
        $escrow = $this->escrow();
        $escrow->reserve(self::hold('a', 30));

        $payouts = [new Payout(World::bea(), 20), new Payout(World::cy(), 6)];

        $escrow->confirm(self::hold('a', 30), $payouts);
        $escrow->confirm(self::hold('a', 30), $payouts);

        self::assertSame(20, $this->balance(World::bea()));
        self::assertSame(6, $this->balance(World::cy()));
        self::assertSame(74, $this->balance(World::ana()));
    }

    public function testConfirmingAHoldNeverHeldHoldsItFirst(): void
    {
        $escrow = $this->escrow();

        $escrow->confirm(self::hold('never', 5), [new Payout(World::bea(), 5)]);
        $escrow->confirm(self::hold('never', 5), [new Payout(World::bea(), 5)]);

        self::assertSame(5, $this->balance(World::bea()));
        self::assertSame(95, $this->balance(World::ana()));

        $this->expectException(InsufficientPoints::class);

        $escrow->confirm(self::hold('too-much', 96), [new Payout(World::bea(), 96)]);
    }

    public function testAConfirmedHoldPaidInFullGivesNothingBack(): void
    {
        $escrow = $this->escrow();
        $escrow->reserve(self::hold('a', 7, 'gems'));
        $escrow->reserve(self::hold('b', 7));

        $escrow->confirm(self::hold('a', 7, 'gems'), [new Payout(World::bea(), 7)]);

        self::assertSame(7, $this->balance(World::bea(), 'gems'));
        self::assertSame(0, $this->balance(World::ana(), 'gems'));
        self::assertSame(0, $this->balance(World::bea()));
        self::assertSame(93, $this->balance(World::ana()));
    }

    public function testPayoutsOverTheHoldAreRefused(): void
    {
        $escrow = $this->escrow();
        $escrow->reserve(self::hold('a', 30));

        try {
            $escrow->confirm(self::hold('a', 30), [new Payout(World::bea(), 20), new Payout(World::cy(), 11)]);
            self::fail('31 points are over the 30 held.');
        } catch (EscrowConflict $escrowConflict) {
            self::assertStringContainsString('31 points, over the 30 held', $escrowConflict->getMessage());
        }

        self::assertSame(0, $this->balance(World::bea()));
        $escrow->confirm(self::hold('a', 30), [new Payout(World::bea(), 30)]);
        self::assertSame(30, $this->balance(World::bea()));
    }

    public function testACancelledHoldComesBackOnce(): void
    {
        $escrow = $this->escrow();
        $escrow->reserve(self::hold('a', 30));
        $escrow->reserve(self::hold('b', 10));

        $escrow->cancel(self::hold('a', 30));
        $escrow->cancel(self::hold('a', 30));
        $escrow->cancel(self::hold('never', 5));

        self::assertSame(90, $this->balance(World::ana()));
    }

    abstract protected function escrow(): BountyEscrow;

    abstract protected function balance(ResourceReference $member, string $unit = 'points'): int;

    protected static function hold(string $key, int $amount, string $unit = 'points'): BountyHold
    {
        $at = new DateTimeImmutable('2026-11-14T01:00:00Z');

        return new BountyHold($key, World::ana(), $amount, $unit, $at, $at->modify('+10 days'));
    }
}
