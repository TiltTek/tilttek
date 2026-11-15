<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit\Escrow;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Bounties\BountyHold;
use Tilttek\Bounties\Escrow\InMemoryBountyEscrow;
use Tilttek\Bounties\Exception\EscrowConflict;
use Tilttek\Bounties\Payout;
use Tilttek\Bounties\Tests\Unit\Fixture\World;

#[CoversClass(InMemoryBountyEscrow::class)]
final class InMemoryBountyEscrowTest extends BountyEscrowTestCase
{
    private InMemoryBountyEscrow $escrow;

    public function testAnotherHoldUnderTheSameKeyIsRefused(): void
    {
        $escrow = $this->escrow;
        $escrow->reserve(self::hold('a', 30));

        $this->expectException(EscrowConflict::class);
        $this->expectExceptionMessageIsOrContains('Another hold is held under the key "a".');

        $escrow->reserve(self::hold('a', 31));
    }

    public function testOnlyTheSamePayoutsSettleAHoldTheSameWay(): void
    {
        $escrow = $this->escrow;
        $escrow->reserve(self::hold('a', 30));
        $escrow->confirm(self::hold('a', 30), [new Payout(World::bea(), 12)]);
        $escrow->confirm(self::hold('a', 30), [new Payout(World::bea(), 12)]);

        foreach ([
            [new Payout(ResourceReference::of('member', 'bea1'), 2)],
            [new Payout(World::bea(), 2)],
            [new Payout(World::bea(), 1)],
            [new Payout(World::cy(), 12)],
            [],
        ] as $n => $payouts) {
            try {
                $escrow->confirm(self::hold('a', 30), $payouts);
                self::fail(\sprintf('The payouts %d settle the hold another way.', $n));
            } catch (EscrowConflict $escrowConflict) {
                self::assertStringContainsString('settled already, another way', $escrowConflict->getMessage());
            }
        }

        self::assertSame(12, $escrow->balance(World::bea()));
    }

    public function testAHoldIsSettledOneWayOnly(): void
    {
        $escrow = $this->escrow;
        $escrow->reserve(self::hold('a', 30));
        $escrow->reserve(self::hold('b', 30));
        $escrow->reserve(self::hold('c', 30));
        $escrow->confirm(self::hold('a', 30), [new Payout(World::bea(), 30)]);
        $escrow->cancel(self::hold('b', 30));

        foreach ([
            static fn () => $escrow->cancel(self::hold('a', 30)),
            static fn () => $escrow->confirm(self::hold('a', 30), [new Payout(World::cy(), 30)]),
            static fn () => $escrow->confirm(self::hold('b', 30), []),
            static fn () => $escrow->cancel(self::hold('c', 29)),
        ] as $n => $move) {
            try {
                $move();
                self::fail(\sprintf('Move %d is refused.', $n));
            } catch (EscrowConflict $escrowConflict) {
                self::assertStringContainsString('settled already, another way', $escrowConflict->getMessage());
            }
        }

        self::assertSame(30, $escrow->balance(World::bea()));
        self::assertSame(0, $escrow->balance(World::cy()));
        self::assertSame(30, $escrow->held('c'));
    }

    public function testTheHeldPointsAreThoseNotYetSettled(): void
    {
        $escrow = $this->escrow;
        $escrow->reserve(self::hold('a', 30));

        self::assertSame(30, $escrow->held('a'));
        self::assertSame(0, $escrow->held('b'));

        $escrow->confirm(self::hold('a', 30), []);

        self::assertSame(0, $escrow->held('a'));
        self::assertSame(100, $escrow->balance(World::ana()));
    }

    public function testAHoldOfTheSamePointsOverAnotherTimeIsTheSameHold(): void
    {
        $escrow = $this->escrow;
        $escrow->reserve(self::hold('a', 30));

        $at = new DateTimeImmutable('2027-01-01T00:00:00Z');

        self::assertFalse($escrow->reserve(new BountyHold('a', World::ana(), 30, 'points', $at, $at->modify('+1 day'))));
    }

    #[Override]
    protected function setUp(): void
    {
        $this->escrow = new InMemoryBountyEscrow();
        $this->escrow->fund(World::ana(), 100);
        $this->escrow->fund(World::ana(), 7, 'gems');
    }

    #[Override]
    protected function escrow(): InMemoryBountyEscrow
    {
        return $this->escrow;
    }

    #[Override]
    protected function balance(ResourceReference $member, string $unit = 'points'): int
    {
        return $this->escrow->balance($member, $unit);
    }
}
