<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit\Escrow;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Tilttek\FriendChallenges\Contract\PrizeEscrow;
use Tilttek\FriendChallenges\Escrow\PointsPrizeEscrow;
use Tilttek\FriendChallenges\Payout;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\World;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\Points;

#[CoversClass(PointsPrizeEscrow::class)]
final class PointsPrizeEscrowTest extends PrizeEscrowTestCase
{
    private Points $points;

    public function testTheMovementsAreWrittenUnderKeysDerivedFromTheHold(): void
    {
        $escrow = $this->escrow();
        $escrow->reserve(self::hold('friend-challenge:c1', 30));
        $escrow->confirm(self::hold('friend-challenge:c1', 30), [new Payout(World::bea(), 25)]);

        $keys = array_map(static fn (LedgerEntry $entry): string => $entry->movement->key->value, $this->points->history(World::ana(), 'points'));
        $won = $this->points->history(World::bea());

        self::assertSame(['welcome', 'friend-challenge:c1', 'friend-challenge:c1:remainder'], $keys);
        self::assertCount(1, $won);
        self::assertSame('friend-challenge:c1:payout:'.hash('xxh128', 'member:bea'), $won[0]->movement->key->value);
        self::assertSame('issuance:friend-challenges', $won[0]->movement->debit->toString());
        self::assertSame('redemption:friend-challenges', $this->points->history(World::ana(), 'points')[1]->movement->credit->toString());
    }

    #[Override]
    protected function setUp(): void
    {
        $this->points = new Points(new InMemoryLedger(), new MockClock('2026-11-14 01:00:00 UTC'), new Currencies(new Currency('points'), new Currency('gems')));
        $this->points->grant(World::ana(), 100, 'welcome');
        $this->points->grant(World::ana(), 7, 'welcome:gems', currency: 'gems');
    }

    #[Override]
    protected function escrow(): PrizeEscrow
    {
        return new PointsPrizeEscrow($this->points);
    }

    #[Override]
    protected function balance(ResourceReference $member, string $unit = 'points'): int
    {
        return $this->points->balance($member, $unit);
    }
}
