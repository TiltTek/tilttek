<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Transfer;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Allowance;
use Tilttek\Exchange\Event\PointsTransferred;
use Tilttek\Exchange\Exception\ExchangeConflict;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Guards;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Tests\Unit\Fixture\Clock;
use Tilttek\Exchange\Tests\Unit\Fixture\JoiningDates;
use Tilttek\Exchange\Tests\Unit\Fixture\RecordingAnnouncer;
use Tilttek\Exchange\Transfer\InMemoryTransferLog;
use Tilttek\Exchange\Transfer\Transfer;
use Tilttek\Exchange\Transfer\Transfers;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;
use Tilttek\Points\Rule\Period;

#[CoversClass(Transfers::class)]
#[CoversClass(InMemoryTransferLog::class)]
#[CoversClass(PointsTransferred::class)]
final class TransfersTest extends TestCase
{
    private Clock $clock;

    private InMemoryTransferLog $log;

    private Points $points;

    private Currencies $currencies;

    private RecordingAnnouncer $announcer;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new Clock('2026-11-07 12:00:00 UTC');
        $this->log = new InMemoryTransferLog();
        $this->currencies = new Currencies(new Currency('points'), new Currency('stars'));
        $this->points = new Points(new InMemoryLedger(), $this->clock, $this->currencies);
        $this->announcer = new RecordingAnnouncer();

        foreach ([1, 2, 3] as $member) {
            $this->points->grant(self::member($member), 1000, 'welcome:'.$member);
            $this->points->grant(self::member($member), 100, 'stars:'.$member, currency: 'stars');
        }
    }

    public function testPointsGoFromOneMemberToAnother(): void
    {
        $transfers = $this->transfers();
        $transfer = $transfers->transfer(self::member(1), self::member(2), 50, 'gift:1');

        self::assertSame(['key' => 'gift:1', 'from' => 'member:1', 'to' => 'member:2', 'amount' => 50, 'currency' => 'points', 'at' => '2026-11-07T12:00:00+00:00'], $transfer->toArray());
        self::assertSame(950, $this->points->balance(self::member(1)));
        self::assertSame(1050, $this->points->balance(self::member(2)));
        self::assertSame(['PointsTransferred'], $this->announcer->told());

        // The same key gives once, whatever the clock says.
        $this->clock->set('2026-11-07 13:00:00 UTC');
        self::assertEquals($transfer, $transfers->transfer(self::member(1), self::member(2), 50, 'gift:1'));
        self::assertSame(950, $this->points->balance(self::member(1)));
        self::assertSame([], $this->announcer->told());

        self::assertSame('stars', $transfers->transfer(self::member(1), self::member(3), 10, 'gift:2', 'stars')->currency);
        self::assertSame(90, $this->points->balance(self::member(1), 'stars'));
        self::assertSame(['gift:1', 'gift:2'], $this->keys($transfers->ofMember(self::member(1))));
        self::assertSame(['gift:2', 'gift:1'], $this->keys($transfers->journal()));
        self::assertSame(50, $transfers->given(self::member(1)));
        self::assertSame(10, $transfers->given(self::member(1), 'stars'));
    }

    public function testTheJournalIsReadWithinItsBounds(): void
    {
        $transfers = $this->transfers();

        foreach (range(1, 3) as $gift) {
            $transfers->transfer(self::member(1), self::member(2), 1, 'gift:'.$gift);
        }

        self::assertSame(['gift:3'], $this->keys($transfers->journal(0)));
        self::assertSame(['gift:3'], $this->keys($transfers->journal(1)));
        self::assertSame(['gift:3', 'gift:2'], $this->keys($transfers->journal(2)));
        self::assertCount(3, $transfers->journal(5000));
        self::assertSame(1000, Transfers::MAX_JOURNAL);
    }

    public function testTheJournalShowsFiftyTransfersUnlessAskedOtherwise(): void
    {
        foreach (range(1, 51) as $gift) {
            $this->log->record(new Transfer(ExchangeKey::fromString('gift:'.$gift), self::member(1), self::member(2), 1, 'points', $this->clock->now()));
        }

        self::assertCount(50, $this->transfers()->journal());
    }

    public function testATransferInterruptedBeforeItsJournalIsWrittenAgain(): void
    {
        $this->points->transfer(self::member(1), self::member(2), 50, 'exchange:transfer:gift:1');

        $this->transfers()->transfer(self::member(1), self::member(2), 50, 'gift:1');
        self::assertSame(950, $this->points->balance(self::member(1)));
        self::assertSame(['PointsTransferred'], $this->announcer->told());
        self::assertSame(['gift:1'], $this->keys($this->log->latest(10)));
    }

    public function testTheSameKeyForAnotherTransferIsAConflict(): void
    {
        $transfers = $this->transfers();
        $transfers->transfer(self::member(1), self::member(2), 50, 'gift:1');

        $this->expectException(ExchangeConflict::class);
        $this->expectExceptionMessageIsOrContains('The key "gift:1" is already written for another transfer.');

        $transfers->transfer(self::member(1), self::member(2), 51, 'gift:1');
    }

    public function testAMemberGivesNoMoreThanTheirBalance(): void
    {
        try {
            $this->transfers()->transfer(self::member(1), self::member(2), 1001, 'gift:1');
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints) {
            self::assertNull($this->log->find(ExchangeKey::fromString('gift:1')));
            self::assertSame([], $this->announcer->told());
        }
    }

    public function testTheGuards(): void
    {
        $transfers = $this->transfers(new Guards(new Allowance(100, Period::Day), 'P7D', 'UTC', new JoiningDates(['member:1' => '2026-01-01', 'member:2' => '2026-01-01', 'member:3' => '2026-11-06'])));

        self::assertSame(Refusal::SelfExchange, $this->refusal(static fn (): Transfer => $transfers->transfer(self::member(1), self::member(1), 10, 'gift:1'))->refusal);
        self::assertSame(Refusal::NewMember, $this->refusal(static fn (): Transfer => $transfers->transfer(self::member(1), self::member(3), 10, 'gift:1'))->refusal);

        $transfers->transfer(self::member(1), self::member(2), 60, 'gift:1');
        $transfers->transfer(self::member(1), self::member(2), 40, 'gift:2');
        self::assertSame(100, $transfers->given(self::member(1)));
        self::assertSame(Refusal::AllowanceReached, $this->refusal(static fn (): Transfer => $transfers->transfer(self::member(1), self::member(2), 1, 'gift:3'))->refusal);
        self::assertSame(10, $transfers->transfer(self::member(1), self::member(2), 10, 'gift:4', 'stars')->amount);
        self::assertSame(100, $transfers->transfer(self::member(2), self::member(1), 100, 'gift:5')->amount);

        // A transfer already written is returned, the allowance reached or not.
        self::assertSame(60, $transfers->transfer(self::member(1), self::member(2), 60, 'gift:1')->amount);

        $this->clock->set('2026-11-08 00:00:00 UTC');
        self::assertSame(0, $transfers->given(self::member(1)));
        self::assertSame(1, $transfers->transfer(self::member(1), self::member(2), 1, 'gift:3')->amount);
    }

    private function transfers(Guards $guards = new Guards()): Transfers
    {
        return new Transfers($this->log, $this->points, $this->currencies, $this->clock, $guards, $this->announcer);
    }

    private static function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
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

    private function refusal(callable $transfer): ExchangeRefused
    {
        try {
            $transfer();
        } catch (ExchangeRefused $exchangeRefused) {
            return $exchangeRefused;
        }

        self::fail('A refusal was expected.');
    }
}
