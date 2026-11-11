<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Points\Account;
use Tilttek\Points\Currency;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\Lot;
use Tilttek\Points\Lots;
use Tilttek\Points\MovementKey;
use Tilttek\Points\MovementKind;
use Tilttek\Points\Tests\Unit\Ledger\LedgerTestCase;

#[CoversClass(Lots::class)]
#[CoversClass(Lot::class)]
final class LotsTest extends TestCase
{
    private int $position = 0;

    public function testALot(): void
    {
        $lot = new Lot(MovementKey::fromString('a'), $this->at('2026-01-01'), 10, $this->at('2026-02-01'));

        self::assertTrue($lot->isLiveAt($this->at('2026-01-31 23:59:59')));
        self::assertFalse($lot->isLiveAt($this->at('2026-02-01')));
        self::assertNull($lot->expiredBy($this->at('2026-01-31 23:59:59')));
        self::assertSame('2026-02-01', $lot->expiredBy($this->at('2026-02-01'))?->format('Y-m-d'));
        self::assertSame('2026-02-01', $lot->expiredBy($this->at('2026-03-01'))?->format('Y-m-d'));
        self::assertNull($lot->take(10)->expiredBy($this->at('2026-03-01')));
        self::assertSame('2026-02-01', $lot->take(9)->expiredBy($this->at('2026-03-01'))?->format('Y-m-d'));

        $taken = $lot->take(4);
        self::assertSame(['a', '2026-01-01', 6, '2026-02-01'], [$taken->key->value, $taken->creditedAt->format('Y-m-d'), $taken->remaining, $taken->expiresAt?->format('Y-m-d')]);

        $forever = new Lot(MovementKey::fromString('b'), $this->at('2026-01-01'), 10, null);
        self::assertTrue($forever->isLiveAt($this->at('2099-01-01')));
        self::assertNull($forever->expiredBy($this->at('2099-01-01')));
    }

    public function testCreditsOpenLotsThatDebitsTakeFirstInFirstOut(): void
    {
        $lots = $this->lots('P1M');
        $lots->apply($this->entry('a', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-01-10'));
        $lots->apply($this->entry('b', MovementKind::Granted, 'issuance:manual', 'member:member:1', 20, '2026-01-05'));
        $lots->apply($this->entry('c', MovementKind::Earned, 'issuance:games', 'member:member:1', 30, '2026-01-20'));
        $lots->apply($this->entry('other', MovementKind::Granted, 'issuance:manual', 'member:member:2', 99, '2026-01-01'));
        $lots->apply($this->entry('stars', MovementKind::Granted, 'issuance:manual', 'member:member:1', 99, '2026-01-01', 'stars'));

        self::assertSame([['b', 20, '2026-02-05'], ['a', 10, '2026-02-10'], ['c', 30, '2026-02-20']], $this->open($lots));

        $lots->apply($this->entry('d', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 25, '2026-01-25'));
        self::assertSame([['a', 5, '2026-02-10'], ['c', 30, '2026-02-20']], $this->open($lots));
        self::assertSame(0, $lots->debt());
    }

    public function testADebitThatNamesALotTakesFromItFirst(): void
    {
        $lots = $this->lots(null);
        $lots->apply($this->entry('a', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-01-01'));
        $lots->apply($this->entry('b', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-01-02'));
        $lots->apply($this->entry('c', MovementKind::Reversed, 'member:member:1', 'issuance:games', 4, '2026-01-02', reverses: 'b'));

        self::assertSame([['a', 10, null], ['b', 6, null]], $this->open($lots));

        $lots->apply($this->entry('d', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 3, '2026-01-03'));
        $lots->apply($this->entry('e', MovementKind::Reversed, 'member:member:1', 'issuance:games', 10, '2026-01-01', reverses: 'a'));
        self::assertSame([['b', 3, null]], $this->open($lots));
    }

    public function testDebitsTakeFromLiveLotsBeforeExpiredOnes(): void
    {
        $lots = $this->lots('P1M');
        $lots->apply($this->entry('a', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-01-01'));
        $lots->apply($this->entry('b', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-01-15'));
        $lots->apply($this->entry('c', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 12, '2026-02-01'));

        self::assertSame([['a', 8, '2026-02-01']], $this->open($lots));

        $lots->apply($this->entry('d', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 1, '2026-01-31 23:59:59'));
        self::assertSame([['a', 7, '2026-02-01']], $this->open($lots));
    }

    public function testWhatNoLotCoversIsADebtTheNextCreditsPay(): void
    {
        $lots = $this->lots('P1M');
        $lots->apply($this->entry('a', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-01-01'));
        $lots->apply($this->entry('b', MovementKind::Reversed, 'member:member:1', 'issuance:games', 25, '2026-01-01', reverses: 'zzz'));

        self::assertSame([], $this->open($lots));
        self::assertSame(15, $lots->debt());

        $lots->apply($this->entry('c', MovementKind::Earned, 'issuance:games', 'member:member:1', 5, '2026-01-02'));
        self::assertSame([], $this->open($lots));
        self::assertSame(10, $lots->debt());

        $lots->apply($this->entry('d', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-01-03'));
        self::assertSame([], $this->open($lots));
        self::assertSame(0, $lots->debt());

        $lots->apply($this->entry('e', MovementKind::Earned, 'issuance:games', 'member:member:1', 1, '2026-01-04'));
        self::assertSame([['e', 1, '2026-02-04']], $this->open($lots));

        $lots->apply($this->entry('f', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 4, '2026-01-05'));
        $lots->apply($this->entry('g', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 2, '2026-01-05'));
        self::assertSame([], $this->open($lots));
        self::assertSame(5, $lots->debt());

        $lots->apply($this->entry('h', MovementKind::Earned, 'issuance:games', 'member:member:1', 8, '2026-01-06'));
        self::assertSame([['h', 3, '2026-02-06']], $this->open($lots));
        self::assertSame(0, $lots->debt());
    }

    public function testCreditsOfTheSameMomentAreTakenInTheOrderWritten(): void
    {
        $lots = $this->lots(null);
        $lots->apply($this->entry('a', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-01-01'));
        $lots->apply($this->entry('b', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-01-01'));
        $lots->apply($this->entry('c', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-01-01'));
        $lots->apply($this->entry('d', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 15, '2026-01-02'));

        self::assertSame([['b', 5, null], ['c', 10, null]], $this->open($lots));
    }

    private function lots(?string $lifetime): Lots
    {
        return new Lots(Account::fromString('member:member:1'), new Currency('points', $lifetime));
    }

    private function entry(string $key, MovementKind $kind, string $debit, string $credit, int $amount, string $occurredAt, string $currency = 'points', ?string $reverses = null): LedgerEntry
    {
        return new LedgerEntry(++$this->position, LedgerTestCase::movement($key, $kind, $debit, $credit, $amount, $occurredAt, $currency, reverses: $reverses), $this->at('2026-11-07'));
    }

    /**
     * @return list<array{string, int, ?string}>
     */
    private function open(Lots $lots): array
    {
        return array_map(static fn (Lot $lot): array => [$lot->key->value, $lot->remaining, $lot->expiresAt?->format('Y-m-d')], $lots->open());
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }
}
