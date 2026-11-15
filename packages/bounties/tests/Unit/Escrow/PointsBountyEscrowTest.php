<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit\Escrow;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Tilttek\Bounties\Contract\BountyEscrow;
use Tilttek\Bounties\Escrow\PointsBountyEscrow;
use Tilttek\Bounties\Payout;
use Tilttek\Bounties\Tests\Unit\Fixture\World;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\Points;

#[CoversClass(PointsBountyEscrow::class)]
final class PointsBountyEscrowTest extends BountyEscrowTestCase
{
    private Points $points;

    public function testTheMovementsAreWrittenUnderKeysDerivedFromTheHold(): void
    {
        $escrow = $this->escrow();
        $escrow->reserve(self::hold('bounty:b1', 30));
        $escrow->confirm(self::hold('bounty:b1', 30), [new Payout(World::bea(), 25)]);

        $keys = array_map(static fn (LedgerEntry $entry): string => $entry->movement->key->value, $this->points->history(World::ana(), 'points'));
        $won = $this->points->history(World::bea());

        self::assertSame(['welcome', 'bounty:b1', 'bounty:b1:remainder'], $keys);
        self::assertCount(1, $won);
        self::assertSame('bounty:b1:payout:'.hash('xxh128', 'member:bea'), $won[0]->movement->key->value);
        self::assertSame('issuance:bounties', $won[0]->movement->debit->toString());
        self::assertSame('redemption:bounties', $this->points->history(World::ana(), 'points')[1]->movement->credit->toString());
    }

    #[Override]
    protected function setUp(): void
    {
        $this->points = new Points(new InMemoryLedger(), new MockClock('2026-11-14 01:00:00 UTC'), new Currencies(new Currency('points'), new Currency('gems')));
        $this->points->grant(World::ana(), 100, 'welcome');
        $this->points->grant(World::ana(), 7, 'welcome:gems', currency: 'gems');
    }

    #[Override]
    protected function escrow(): BountyEscrow
    {
        return new PointsBountyEscrow($this->points);
    }

    #[Override]
    protected function balance(ResourceReference $member, string $unit = 'points'): int
    {
        return $this->points->balance($member, $unit);
    }
}
