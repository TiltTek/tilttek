<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Transfer;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Transfer\Transfer;
use Tilttek\Exchange\Transfer\TransferLog;

/**
 * The contract every journal of transfers passes: a journal of the application's storage extends this case and
 * builds itself in `log()`.
 */
abstract class TransferLogTestCase extends TestCase
{
    abstract protected function log(): TransferLog;

    public function testATransferIsWrittenOnceByItsKey(): void
    {
        $log = $this->log();

        self::assertTrue($log->record(self::transfer('a', 1, 2, 10, '12:00')));
        self::assertFalse($log->record(self::transfer('a', 3, 4, 99, '13:00')));
        self::assertSame(['key' => 'a', 'from' => 'member:1', 'to' => 'member:2', 'amount' => 10, 'currency' => 'points', 'at' => '2026-11-07T12:00:00+00:00'], $this->utc($log->find(ExchangeKey::fromString('a'))));
        self::assertNull($log->find(ExchangeKey::fromString('b')));
    }

    public function testWhatAMemberGaveOverAPeriod(): void
    {
        $log = $this->log();
        $log->record(self::transfer('a', 1, 2, 10, '11:59'));
        $log->record(self::transfer('b', 1, 2, 20, '12:00'));
        $log->record(self::transfer('c', 1, 3, 30, '12:30'));
        $log->record(self::transfer('d', 1, 2, 40, '13:00'));
        $log->record(self::transfer('e', 2, 1, 50, '12:15'));
        $log->record(self::transfer('f', 1, 2, 60, '12:15', 'stars'));

        self::assertSame(100, $log->given(self::member(1), 'points', null, null));
        self::assertSame(50, $log->given(self::member(1), 'points', self::at('12:00'), self::at('13:00')));
        self::assertSame(90, $log->given(self::member(1), 'points', self::at('12:00'), null));
        self::assertSame(60, $log->given(self::member(1), 'points', null, self::at('13:00')));
        self::assertSame(60, $log->given(self::member(1), 'stars', null, null));
        self::assertSame(50, $log->given(self::member(2), 'points', null, null));
        self::assertSame(0, $log->given(self::member(3), 'points', null, null));
    }

    public function testTheJournalOfAMemberAndOfEveryone(): void
    {
        $log = $this->log();
        $log->record(self::transfer('a', 1, 2, 10, '12:00'));
        $log->record(self::transfer('b', 3, 1, 20, '12:01'));
        $log->record(self::transfer('c', 2, 3, 30, '12:02'));

        self::assertSame(['a', 'b'], $this->keys($log->ofMember(self::member(1))));
        self::assertSame(['a', 'c'], $this->keys($log->ofMember(self::member(2))));
        self::assertSame([], $log->ofMember(self::member(4)));
        self::assertSame(['c', 'b', 'a'], $this->keys($log->latest(10)));
        self::assertSame(['c', 'b'], $this->keys($log->latest(2)));
    }

    public function testAnErasedMemberLeavesTheirTransfersToTheOthers(): void
    {
        $log = $this->log();
        $log->record(self::transfer('a', 1, 2, 10, '12:00'));
        $log->record(self::transfer('b', 3, 1, 20, '12:01'));
        $log->record(self::transfer('c', 2, 3, 30, '12:02'));

        self::assertSame(2, $log->eraseMember(self::member(1)));
        self::assertSame(0, $log->eraseMember(self::member(1)));
        self::assertSame([], $log->ofMember(self::member(1)));
        self::assertSame('erased:member', $log->find(ExchangeKey::fromString('a'))?->from->toString());
        self::assertSame('member:2', $log->find(ExchangeKey::fromString('a'))->to->toString());
        self::assertSame('member:3', $log->find(ExchangeKey::fromString('b'))?->from->toString());
        self::assertSame('erased:member', $log->find(ExchangeKey::fromString('b'))->to->toString());
        self::assertSame(10, $log->find(ExchangeKey::fromString('a'))->amount);
        self::assertSame(['a', 'c'], $this->keys($log->ofMember(self::member(2))));
    }

    protected static function transfer(string $key, int $from, int $to, int $amount, string $at, string $currency = 'points'): Transfer
    {
        return new Transfer(ExchangeKey::fromString($key), self::member($from), self::member($to), $amount, $currency, self::at($at));
    }

    protected static function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    protected static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-07 '.$time.':00 UTC');
    }

    /**
     * @return array<string, int|string>
     */
    private function utc(?Transfer $transfer): array
    {
        self::assertInstanceOf(Transfer::class, $transfer);

        return new Transfer($transfer->key, $transfer->from, $transfer->to, $transfer->amount, $transfer->currency, $transfer->at->setTimezone(new DateTimeZone('UTC')))->toArray();
    }

    /**
     * @param list<Transfer> $transfers
     *
     * @return list<string>
     */
    private function keys(array $transfers): array
    {
        return array_map(static fn (Transfer $transfer): string => $transfer->key->value, $transfers);
    }
}
