<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Transfer;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Transfer\Transfer;
use Tilttek\Points\Movement;

#[CoversClass(Transfer::class)]
final class TransferTest extends TestCase
{
    public function testATransferAndItsValues(): void
    {
        $transfer = $this->transfer(1, 2, 50);

        self::assertSame('exchange:transfer:gift:1', $transfer->ledgerKey());
        self::assertSame(['key' => 'gift:1', 'from' => 'member:1', 'to' => 'member:2', 'amount' => 50, 'currency' => 'points', 'at' => '2026-11-07T12:00:00+00:00'], $transfer->toArray());
        self::assertTrue($transfer->sameAs($this->transfer(1, 2, 50, 'points', '2026-11-08 00:00:00 UTC')));
        self::assertFalse($transfer->sameAs($this->transfer(3, 2, 50)));
        self::assertFalse($transfer->sameAs($this->transfer(1, 3, 50)));
        self::assertFalse($transfer->sameAs($this->transfer(1, 2, 51)));
        self::assertFalse($transfer->sameAs($this->transfer(1, 2, 50, 'stars')));
        self::assertSame(1, $this->transfer(1, 2, 1)->amount);
        self::assertSame(Movement::MAX_AMOUNT, $this->transfer(1, 2, Movement::MAX_AMOUNT)->amount);
    }

    public function testATransferGivesAPoint(): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A transfer gives 1 to %d points; "gift:1" gives 0.', Movement::MAX_AMOUNT));

        $this->transfer(1, 2, 0);
    }

    public function testATransferGivesNoMoreThanAMovementMoves(): void
    {
        $this->expectException(InvalidExchange::class);

        $this->transfer(1, 2, Movement::MAX_AMOUNT + 1);
    }

    private function transfer(int $from, int $to, int $amount, string $currency = 'points', string $at = '2026-11-07 12:00:00 UTC'): Transfer
    {
        return new Transfer(ExchangeKey::fromString('gift:1'), ResourceReference::of('member', (string) $from), ResourceReference::of('member', (string) $to), $amount, $currency, new DateTimeImmutable($at));
    }
}
