<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Points\Account;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Exception\UnknownCurrency;
use Tilttek\Points\Exception\UnknownMovement;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\MovementKey;
use Tilttek\Points\Points;
use Tilttek\Points\Recorded;
use Tilttek\Points\Tests\Unit\Fixture\Clock;
use Tilttek\Points\Tests\Unit\Fixture\ReadCountingLedger;

#[CoversClass(Points::class)]
#[CoversClass(UnknownMovement::class)]
final class PointsTest extends TestCase
{
    private InMemoryLedger $ledger;

    private Clock $clock;

    private Points $points;

    #[Override]
    protected function setUp(): void
    {
        $this->ledger = new InMemoryLedger();
        $this->clock = new Clock('2026-11-07 12:00:00 UTC');
        $this->points = new Points($this->ledger, $this->clock, new Currencies(new Currency('points'), new Currency('stars', 'P1M')));
    }

    public function testGrantingAndRevoking(): void
    {
        $granted = $this->points->grant($this->member(1), 30, 'welcome:1');
        self::assertTrue($granted->new);
        self::assertSame(['welcome:1', 'granted', 'points', 'issuance:manual', 'member:member:1', 30, '2026-11-07 12:00:00', null, null], $this->values($granted));
        self::assertSame('2026-11-07 12:00:00', $granted->entry->at->format('Y-m-d H:i:s'));

        self::assertSame(['contest', 'granted', 'stars', 'issuance:contest:2026', 'member:member:1', 5, '2026-11-07 12:00:00', null, null], $this->values($this->points->grant($this->member(1), 5, 'contest', 'contest:2026', 'stars')));
        self::assertFalse($this->points->grant($this->member(1), 30, 'welcome:1')->new);

        $this->clock->set('2026-11-08 09:00:00 UTC');
        self::assertSame(['fix:1', 'revoked', 'points', 'member:member:1', 'issuance:manual', 40, '2026-11-08 09:00:00', null, null], $this->values($this->points->revoke($this->member(1), 40, 'fix:1')));
        self::assertSame(['fix:2', 'revoked', 'stars', 'member:member:1', 'issuance:contest:2026', 1, '2026-11-08 09:00:00', null, null], $this->values($this->points->revoke($this->member(1), 1, 'fix:2', 'contest:2026', 'stars')));

        self::assertSame(-10, $this->points->balance($this->member(1)));
        self::assertSame(4, $this->points->balance($this->member(1), 'stars'));
        self::assertSame(0, $this->points->balance($this->member(2)));
    }

    public function testTheKeysOfDecidedMovementsCannotLookDerived(): void
    {
        $this->expectException(InvalidPoints::class);
        $this->points->grant($this->member(1), 1, '#earn:x');
    }

    public function testAnUnknownCurrency(): void
    {
        try {
            $this->points->balance($this->member(1), 'crowns');
            self::fail('An unknown currency was expected to be refused.');
        } catch (UnknownCurrency $unknownCurrency) {
            self::assertSame('No currency of points is named "crowns".', $unknownCurrency->getMessage());
        }

        $this->expectException(UnknownCurrency::class);
        $this->points->history($this->member(1), 'crowns');
    }

    public function testAHistory(): void
    {
        $this->points->grant($this->member(1), 1, 'a');
        $this->points->grant($this->member(2), 1, 'b');
        $this->points->grant($this->member(1), 1, 'c', currency: 'stars');
        $this->points->grant($this->member(1), 1, 'd');

        self::assertSame([1, 3, 4], $this->positions($this->points->history($this->member(1))));
        self::assertSame([1, 4], $this->positions($this->points->history($this->member(1), 'points')));
        self::assertSame([3, 4], $this->positions($this->points->history($this->member(1), null, 1)));
        self::assertSame([3], $this->positions($this->points->history($this->member(1), null, 1, 1)));
        self::assertSame([1, 3, 4], $this->positions($this->points->history($this->member(1), null, 0, 1000)));

        foreach (range(5, 55) as $key) {
            $this->points->grant($this->member(1), 1, (string) $key);
        }

        self::assertCount(50, $this->points->history($this->member(1)));
        self::assertSame(51, $this->points->history($this->member(1))[49]->position);

        $this->expectExceptionObject(InvalidPoints::because('A history holds at most 1000 movements; 1001 is too many.'));
        $this->points->history($this->member(1), null, 0, 1001);
    }

    public function testSpendingNeverMakesABalanceNegative(): void
    {
        $this->points->grant($this->member(1), 30, 'a');

        try {
            $this->points->spend($this->member(1), 31, 'order:1', 'order:1');
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints $insufficientPoints) {
            self::assertSame('The account "member:member:1" holds 30 points; 31 cannot be paid from it.', $insufficientPoints->getMessage());
        }

        $spent = $this->points->spend($this->member(1), 30, 'order:1', 'order:1');
        self::assertSame(['order:1', 'spent', 'points', 'member:member:1', 'redemption:order:1', 30, '2026-11-07 12:00:00', null, null], $this->values($spent));
        self::assertSame(0, $this->points->balance($this->member(1)));
        self::assertFalse($this->points->spend($this->member(1), 30, 'order:1', 'order:1')->new);
    }

    public function testRefundingASpending(): void
    {
        $this->points->grant($this->member(1), 30, 'a', currency: 'stars');
        $this->points->spend($this->member(1), 20, 'order:1', 'reward:7', 'stars');

        $this->clock->set('2026-11-09 08:00:00 UTC');

        $refunded = $this->points->refund('order:1');
        self::assertTrue($refunded->new);
        self::assertSame(['#refund:order:1', 'refunded', 'stars', 'redemption:reward:7', 'member:member:1', 20, '2026-11-09 08:00:00', null, 'order:1'], $this->values($refunded));
        self::assertSame('2026-11-09 08:00:00', $refunded->entry->at->format('Y-m-d H:i:s'));
        self::assertSame(30, $this->points->balance($this->member(1), 'stars'));
        self::assertFalse($this->points->refund('order:1')->new);
        self::assertSame(30, $this->points->balance($this->member(1), 'stars'));

        foreach (['order:2', 'a'] as $key) {
            try {
                $this->points->refund($key);
                self::fail('A refund of no spending was expected to be refused.');
            } catch (UnknownMovement $unknownMovement) {
                self::assertSame(\sprintf('No spending is written under the key "%s".', $key), $unknownMovement->getMessage());
            }
        }
    }

    public function testTransferring(): void
    {
        $this->points->grant($this->member(1), 30, 'a');

        try {
            $this->points->transfer($this->member(1), $this->member(2), 31, 'gift:1');
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints) {
            self::assertSame(0, $this->points->balance($this->member(2)));
        }

        self::assertSame(['gift:1', 'transferred', 'points', 'member:member:1', 'member:member:2', 30, '2026-11-07 12:00:00', null, null], $this->values($this->points->transfer($this->member(1), $this->member(2), 30, 'gift:1')));
        self::assertSame(['gift:2', 'transferred', 'points', 'member:member:2', 'member:member:3', 10, '2026-11-07 12:00:00', null, null], $this->values($this->points->transfer($this->member(2), $this->member(3), 10, 'gift:2', 'points')));
        self::assertSame(20, $this->points->balance($this->member(2)));

        $this->expectException(InvalidPoints::class);
        $this->points->transfer($this->member(3), $this->member(3), 1, 'gift:3');
    }

    public function testCreditsExpireFirstInFirstOut(): void
    {
        $this->clock->set('2026-01-10 00:00:00 UTC');
        $this->points->grant($this->member(1), 10, 'a', currency: 'stars');
        $this->clock->set('2026-01-20 00:00:00 UTC');
        $this->points->grant($this->member(1), 10, 'b', currency: 'stars');
        $this->points->spend($this->member(1), 4, 'c', 'reward:1', 'stars');
        $this->points->grant($this->member(1), 99, 'd');

        $this->clock->set('2026-02-10 00:00:00 UTC');
        $reads = new ReadCountingLedger($this->ledger);
        self::assertSame(0, new Points($reads, $this->clock, new Currencies(new Currency('points'), new Currency('stars', 'P1M')))->expire($this->member(1)));
        self::assertSame(0, $reads->statements);
        self::assertSame(0, $this->points->expire($this->member(1)));
        self::assertSame(6, $this->points->expire($this->member(1), 'stars'));
        self::assertSame(['#expire:a', 'expired', 'stars', 'member:member:1', 'expiry:expired', 6, '2026-02-10 00:00:00', null, 'a'], $this->values(new Recorded($this->ledger->find(MovementKey::fromString('#expire:a')) ?? self::fail(), true)));
        self::assertSame('2026-02-10 00:00:00', $this->ledger->find(MovementKey::fromString('#expire:a'))?->at->format('Y-m-d H:i:s'));
        self::assertSame(0, $this->points->expire($this->member(1), 'stars'));
        self::assertSame(10, $this->points->balance($this->member(1), 'stars'));

        $this->clock->set('2026-03-01 00:00:00 UTC');
        self::assertSame(10, $this->points->expire($this->member(1), 'stars'));
        self::assertSame('2026-02-20 00:00:00', $this->ledger->find(MovementKey::fromString('#expire:b'))?->movement->occurredAt->format('Y-m-d H:i:s'));
        self::assertSame(99, $this->points->balance($this->member(1)));
    }

    public function testTheExpiryReadsTheWholeStatementInBatches(): void
    {
        foreach ([1, 2, 3] as $size) {
            $ledger = new InMemoryLedger();
            $points = new Points($ledger, $this->clock, new Currencies(new Currency('stars', 'P1D')), $size);
            $this->clock->set('2026-01-01 00:00:00 UTC');

            foreach (['a', 'b', 'c', 'd'] as $key) {
                $points->grant($this->member(1), 1, $key);
            }

            $this->clock->set('2026-01-02 00:00:00 UTC');
            self::assertSame(4, $points->expire($this->member(1)), (string) $size);
        }

        self::assertSame(500, Points::DEFAULT_BATCH_SIZE);

        $this->expectExceptionObject(InvalidPoints::because('A batch holds at least one entry; 0 is not a batch size.'));
        new Points(new InMemoryLedger(), $this->clock, new Currencies(new Currency('stars')), 0);
    }

    public function testSpendingAndTransferringExpireTheCreditsDueFirst(): void
    {
        $this->clock->set('2026-01-01 00:00:00 UTC');
        $this->points->grant($this->member(1), 10, 'a', currency: 'stars');
        $this->points->grant($this->member(2), 10, 'b', currency: 'stars');

        $this->clock->set('2026-02-01 00:00:00 UTC');

        try {
            $this->points->spend($this->member(1), 1, 'c', 'reward:1', 'stars');
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints $insufficientPoints) {
            self::assertSame('The account "member:member:1" holds 0 stars; 1 cannot be paid from it.', $insufficientPoints->getMessage());
        }

        try {
            $this->points->transfer($this->member(2), $this->member(1), 1, 'd', 'stars');
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints $insufficientPoints) {
            self::assertSame('The account "member:member:2" holds 0 stars; 1 cannot be paid from it.', $insufficientPoints->getMessage());
        }
    }

    public function testEveryMemberDueExpires(): void
    {
        $points = new Points($this->ledger, $this->clock, new Currencies(new Currency('points'), new Currency('stars', 'P1M'), new Currency('crowns', 'P1Y')));
        $this->clock->set('2026-01-01 00:00:00 UTC');
        $points->grant($this->member(1), 10, 'a', currency: 'stars');
        $points->grant($this->member(2), 20, 'b', currency: 'stars');
        $points->grant($this->member(3), 40, 'c', currency: 'crowns');
        $points->grant($this->member(4), 80, 'd');

        $this->clock->set('2026-01-15 00:00:00 UTC');
        $points->grant($this->member(5), 160, 'e', currency: 'stars');

        $this->clock->set('2026-02-01 00:00:00 UTC');
        self::assertSame(30, $points->expireAll());
        self::assertSame(0, $points->expireAll());
        self::assertSame(160, $points->balance($this->member(5), 'stars'));

        $this->clock->set('2027-01-01 00:00:00 UTC');
        self::assertSame(200, $points->expireAll());
        self::assertSame(80, $points->balance($this->member(4)));
    }

    public function testErasingAMember(): void
    {
        $this->points->grant($this->member(1), 30, 'a');
        $this->points->transfer($this->member(1), $this->member(2), 10, 'b');

        $erasure = $this->points->erase($this->member(1));
        self::assertSame([1, 1], [$erasure->deleted, $erasure->detached]);
        self::assertSame(10, $this->points->balance($this->member(2)));
        self::assertSame('erased:member', $this->ledger->find(MovementKey::fromString('b'))?->movement->debit->toString());
        self::assertSame(0, $this->ledger->balance(Account::member($this->member(1)), 'points'));
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    /**
     * @param list<LedgerEntry> $entries
     *
     * @return list<int>
     */
    private function positions(array $entries): array
    {
        return array_map(static fn (LedgerEntry $entry): int => $entry->position, $entries);
    }

    /**
     * @return list<int|string|null>
     */
    private function values(Recorded $recorded): array
    {
        $movement = $recorded->entry->movement;

        return [$movement->key->value, $movement->kind->value, $movement->currency, $movement->debit->toString(), $movement->credit->toString(), $movement->amount, $movement->occurredAt->format('Y-m-d H:i:s'), $movement->cause, $movement->reverses?->value];
    }
}
