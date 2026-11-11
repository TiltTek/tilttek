<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Trade;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Allowance;
use Tilttek\Exchange\Event\OfferAccepted;
use Tilttek\Exchange\Event\OfferCountered;
use Tilttek\Exchange\Event\OfferDeclined;
use Tilttek\Exchange\Event\OfferExpired;
use Tilttek\Exchange\Event\OfferMade;
use Tilttek\Exchange\Event\OfferWithdrawn;
use Tilttek\Exchange\Exception\ExchangeClosed;
use Tilttek\Exchange\Exception\ExchangeConflict;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\Exception\UnknownExchange;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Guards;
use Tilttek\Exchange\ImmediateTransaction;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Tests\Unit\Fixture\Clock;
use Tilttek\Exchange\Tests\Unit\Fixture\JoiningDates;
use Tilttek\Exchange\Tests\Unit\Fixture\LateOfferStore;
use Tilttek\Exchange\Tests\Unit\Fixture\RacingOfferStore;
use Tilttek\Exchange\Tests\Unit\Fixture\RecordingAnnouncer;
use Tilttek\Exchange\Trade\InMemoryInventory;
use Tilttek\Exchange\Trade\InMemoryOfferStore;
use Tilttek\Exchange\Trade\Offer;
use Tilttek\Exchange\Trade\OfferStatus;
use Tilttek\Exchange\Trade\Trades;
use Tilttek\Exchange\Transaction;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;
use Tilttek\Points\Rule\Period;

#[CoversClass(Trades::class)]
#[CoversClass(ImmediateTransaction::class)]
#[CoversClass(OfferMade::class)]
#[CoversClass(OfferAccepted::class)]
#[CoversClass(OfferDeclined::class)]
#[CoversClass(OfferWithdrawn::class)]
#[CoversClass(OfferCountered::class)]
#[CoversClass(OfferExpired::class)]
final class TradesTest extends TestCase
{
    private Clock $clock;

    private InMemoryOfferStore $offers;

    private InMemoryInventory $inventory;

    private Points $points;

    private Currencies $currencies;

    private RecordingAnnouncer $announcer;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new Clock('2026-11-07 12:00:00 UTC');
        $this->offers = new InMemoryOfferStore();
        $this->inventory = new InMemoryInventory();
        $this->currencies = new Currencies(new Currency('points'), new Currency('stars'));
        $this->points = new Points(new InMemoryLedger(), $this->clock, $this->currencies);
        $this->announcer = new RecordingAnnouncer();

        foreach ([1, 2, 3] as $member) {
            $this->points->grant(self::member($member), 100, 'welcome:'.$member);

            foreach ([1, 2, 3] as $card) {
                $this->inventory->give(self::card($member * 10 + $card), self::member($member));
            }
        }

        $this->inventory->give(self::card(19), self::member(1), false);
    }

    public function testAnAcceptedOfferChangesHands(): void
    {
        $trades = $this->trades();
        $offer = $trades->offer('trade:1', self::member(1), self::member(2), [self::card(11), self::card(12)], [self::card(21)], 10, 5);

        self::assertSame(OfferStatus::Open, $offer->status);
        self::assertSame('points', $offer->currency);
        self::assertSame('2026-11-14 12:00:00', $offer->expiresAt->format('Y-m-d H:i:s'));
        self::assertSame(['OfferMade'], $this->announcer->told());
        self::assertSame('trade:1', $this->offers->holder(self::card(11))?->value);
        self::assertSame($offer, $trades->get('trade:1'));
        self::assertSame(1, $trades->made(self::member(1)));

        $this->clock->set('2026-11-08 09:00:00 UTC');
        $accepted = $trades->accept('trade:1', self::member(2));
        self::assertSame(OfferStatus::Accepted, $accepted->status);
        self::assertSame('2026-11-08 09:00:00', $accepted->closedAt?->format('Y-m-d H:i:s'));
        self::assertSame(['OfferAccepted'], $this->announcer->told());
        self::assertSame(['member:2', 'member:2', 'member:1'], [$this->owner(11), $this->owner(12), $this->owner(21)]);
        self::assertSame(95, $this->points->balance(self::member(1)));
        self::assertSame(105, $this->points->balance(self::member(2)));
        self::assertNull($this->offers->holder(self::card(11)));

        self::assertEquals($accepted, $trades->accept('trade:1', self::member(2)));
        self::assertSame([], $this->announcer->told());
        self::assertSame(['trade:1'], array_map(static fn (Offer $offer): string => $offer->key->value, $trades->ofMember(self::member(2))));
    }

    public function testAnOfferWithoutPointsMovesNone(): void
    {
        $trades = $this->trades();
        $trades->offer('trade:1', self::member(1), self::member(2), [self::card(11)], [], currency: 'stars');
        $trades->accept('trade:1', self::member(2));

        self::assertSame('member:2', $this->owner(11));
        self::assertSame(100, $this->points->balance(self::member(1)));
        self::assertSame(100, $this->points->balance(self::member(2)));
        self::assertSame('stars', $trades->get('trade:1')->currency);
    }

    public function testTheSameKeyForAnotherOfferIsAConflict(): void
    {
        $trades = $this->trades();
        $offer = $trades->offer('trade:1', self::member(1), self::member(2), [self::card(11)], [self::card(21)]);
        $this->announcer->told();

        $this->clock->set('2026-11-07 13:00:00 UTC');
        self::assertEquals($offer, $trades->offer('trade:1', self::member(1), self::member(2), [self::card(11)], [self::card(21)]));
        self::assertSame([], $this->announcer->told());

        $this->expectException(ExchangeConflict::class);
        $this->expectExceptionMessageIsOrContains('The key "trade:1" is already written for another offer.');

        $trades->offer('trade:1', self::member(1), self::member(2), [self::card(12)], [self::card(21)]);
    }

    public function testTheItemsOfAnOffer(): void
    {
        $trades = $this->trades();

        $notOwned = $this->refusal(static fn (): Offer => $trades->offer('t', self::member(1), self::member(2), [self::card(21)], []));
        self::assertSame(Refusal::NotOwned, $notOwned->refusal);
        self::assertSame('"card:21" does not belong to "member:1".', $notOwned->getMessage());
        self::assertSame('"card:11" does not belong to "member:2".', $this->refusal(static fn (): Offer => $trades->offer('t', self::member(1), self::member(2), [], [self::card(11)]))->getMessage());
        self::assertSame('"card:99" does not belong to "member:1".', $this->refusal(static fn (): Offer => $trades->offer('t', self::member(1), self::member(2), [self::card(99)], []))->getMessage());

        $bound = $this->refusal(static fn (): Offer => $trades->offer('t', self::member(1), self::member(2), [self::card(19)], []));
        self::assertSame(Refusal::NotTradable, $bound->refusal);
        self::assertSame('"card:19" cannot change hands.', $bound->getMessage());

        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], []);
        $held = $this->refusal(static fn (): Offer => $trades->offer('t2', self::member(1), self::member(3), [self::card(12), self::card(11)], []));
        self::assertSame(Refusal::AlreadyHeld, $held->refusal);
        self::assertSame('"card:11" is held by the offer "t1".', $held->getMessage());
        self::assertNull($this->offers->find(ExchangeKey::fromString('t2')));

        // An item only requested may be asked for by several offers.
        $trades->offer('t3', self::member(2), self::member(1), [self::card(21)], [self::card(11)]);
        $trades->offer('t4', self::member(3), self::member(1), [self::card(31)], [self::card(11)]);
        self::assertSame(3, $this->offers->made(self::member(1), null, null) + $this->offers->made(self::member(2), null, null) + $this->offers->made(self::member(3), null, null));
    }

    public function testAnAcceptanceChecksTheItemsAgain(): void
    {
        $trades = $this->trades();
        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], [self::card(21)]);
        $trades->offer('t2', self::member(2), self::member(3), [self::card(21)], []);

        $held = $this->refusal(static fn (): Offer => $trades->accept('t1', self::member(2)));
        self::assertSame(Refusal::AlreadyHeld, $held->refusal);
        self::assertSame('"card:21" is held by the offer "t2".', $held->getMessage());

        $trades->withdraw('t2', self::member(2));
        $this->inventory->give(self::card(21), self::member(3));
        self::assertSame('"card:21" does not belong to "member:2".', $this->refusal(static fn (): Offer => $trades->accept('t1', self::member(2)))->getMessage());

        $this->inventory->give(self::card(21), self::member(2), false);
        self::assertSame(Refusal::NotTradable, $this->refusal(static fn (): Offer => $trades->accept('t1', self::member(2)))->refusal);

        $this->inventory->give(self::card(21), self::member(2));
        $this->inventory->give(self::card(11), self::member(3));
        self::assertSame('"card:11" does not belong to "member:1".', $this->refusal(static fn (): Offer => $trades->accept('t1', self::member(2)))->getMessage());

        self::assertSame(OfferStatus::Open, $trades->get('t1')->status);
    }

    public function testAnAcceptanceChecksBothBalances(): void
    {
        $trades = $this->trades();
        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], [], 101);
        $trades->offer('t2', self::member(1), self::member(2), [self::card(12)], [], 0, 101);
        $trades->offer('t3', self::member(1), self::member(2), [self::card(13)], [], 100, 100);

        foreach (['t1' => 'member:member:1', 't2' => 'member:member:2'] as $key => $account) {
            try {
                $trades->accept($key, self::member(2));
                self::fail('Insufficient points were expected.');
            } catch (InsufficientPoints $insufficient) {
                self::assertStringContainsString($account, $insufficient->getMessage());
                self::assertSame(OfferStatus::Open, $trades->get($key)->status);
            }
        }

        self::assertSame(OfferStatus::Accepted, $trades->accept('t3', self::member(2))->status);
        self::assertSame(100, $this->points->balance(self::member(1)));
    }

    public function testOnlyTheRecipientAnswersAndOnlyTheProposerWithdraws(): void
    {
        $trades = $this->trades();
        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], []);

        $notParty = $this->refusal(static fn (): Offer => $trades->accept('t1', self::member(1)));
        self::assertSame(Refusal::NotParty, $notParty->refusal);
        self::assertSame('"member:1" cannot answer the offer "t1" this way.', $notParty->getMessage());
        self::assertSame(Refusal::NotParty, $this->refusal(static fn (): Offer => $trades->decline('t1', self::member(3)))->refusal);
        self::assertSame(Refusal::NotParty, $this->refusal(static fn (): Offer => $trades->withdraw('t1', self::member(2)))->refusal);
        self::assertSame(Refusal::NotParty, $this->refusal(static fn (): Offer => $trades->counter('t1', self::member(1), 't2', [], [self::card(21)]))->refusal);
    }

    public function testDecliningAndWithdrawingFreeTheItems(): void
    {
        $trades = $this->trades();
        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], []);
        $trades->offer('t2', self::member(1), self::member(3), [self::card(12)], []);

        $this->announcer->told();

        $declined = $trades->decline('t1', self::member(2));
        self::assertSame(OfferStatus::Declined, $declined->status);
        self::assertSame(['OfferDeclined'], $this->announcer->told());
        self::assertNull($this->offers->holder(self::card(11)));
        self::assertEquals($declined, $trades->decline('t1', self::member(2)));
        self::assertSame([], $this->announcer->told());

        $withdrawn = $trades->withdraw('t2', self::member(1));
        self::assertSame(OfferStatus::Withdrawn, $withdrawn->status);
        self::assertSame(['OfferWithdrawn'], $this->announcer->told());
        self::assertEquals($withdrawn, $trades->withdraw('t2', self::member(1)));

        $closed = $this->closed(static fn (): Offer => $trades->accept('t1', self::member(2)));
        self::assertSame('The offer "t1" is declined.', $closed->getMessage());
        self::assertSame('The offer "t2" is withdrawn.', $this->closed(static fn (): Offer => $trades->decline('t2', self::member(3)))->getMessage());
        self::assertSame('The offer "t1" is declined.', $this->closed(static fn (): Offer => $trades->counter('t1', self::member(2), 't3', [self::card(21)], []))->getMessage());
        self::assertSame(['member:1', 'member:1'], [$this->owner(11), $this->owner(12)]);
    }

    public function testACounterOfferClosesTheFirstAndOpensItsOwn(): void
    {
        $trades = $this->trades();
        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], [self::card(21)], currency: 'stars');

        $this->announcer->told();
        $this->clock->set('2026-11-08 09:00:00 UTC');

        $counter = $trades->counter('t1', self::member(2), 't2', [self::card(22)], [self::card(11), self::card(12)], 0, 3);
        self::assertSame(['member:2', 'member:1', 'stars', 't1', 3], [$counter->proposer->toString(), $counter->recipient->toString(), $counter->currency, $counter->counters?->value, $counter->requestedPoints]);
        self::assertSame(OfferStatus::Countered, $trades->get('t1')->status);
        self::assertSame('2026-11-08 09:00:00', $trades->get('t1')->closedAt?->format('Y-m-d H:i:s'));
        self::assertSame(['OfferCountered'], $this->announcer->told());
        self::assertNull($this->offers->holder(self::card(11)));
        self::assertSame('t2', $this->offers->holder(self::card(22))?->value);

        self::assertEquals($counter, $trades->counter('t1', self::member(2), 't2', [self::card(22)], [self::card(11), self::card(12)], 0, 3));
        self::assertSame([], $this->announcer->told());

        $this->points->grant(self::member(1), 10, 'stars:1', currency: 'stars');
        $trades->accept('t2', self::member(1));
        self::assertSame(['member:1', 'member:2', 'member:2'], [$this->owner(22), $this->owner(11), $this->owner(12)]);
        self::assertSame([7, 3], [$this->points->balance(self::member(1), 'stars'), $this->points->balance(self::member(2), 'stars')]);
    }

    public function testACounterOfferTellsBothOffers(): void
    {
        $trades = $this->trades();
        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], []);

        $this->announcer->told();

        $counter = $trades->counter('t1', self::member(2), 't2', [], [self::card(11)]);
        self::assertSame([0, 0], [$counter->offeredPoints, $counter->requestedPoints]);
        $event = $this->announcer->events[0];
        self::assertInstanceOf(OfferCountered::class, $event);
        self::assertSame(OfferStatus::Countered, $event->offer->status);
        self::assertSame('t1', $event->offer->key->value);
        self::assertSame($counter, $event->counter);
    }

    public function testACounterOfferIsCheckedBeforeTheFirstOfferCloses(): void
    {
        $trades = $this->trades();
        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], []);

        self::assertSame(Refusal::NotOwned, $this->refusal(static fn (): Offer => $trades->counter('t1', self::member(2), 't2', [self::card(31)], []))->refusal);
        self::assertSame(OfferStatus::Open, $trades->get('t1')->status);

        $trades->offer('t0', self::member(2), self::member(3), [self::card(21)], []);
        self::assertSame(Refusal::AlreadyHeld, $this->refusal(static fn (): Offer => $trades->counter('t1', self::member(2), 't2', [self::card(21)], []))->refusal);
        self::assertSame(OfferStatus::Open, $trades->get('t1')->status);
        self::assertNull($this->offers->find(ExchangeKey::fromString('t2')));
    }

    public function testAnUnansweredOfferExpires(): void
    {
        $trades = $this->trades();
        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], []);

        $this->clock->set('2026-11-08 12:00:00 UTC');
        $trades->offer('t2', self::member(1), self::member(2), [self::card(12)], []);
        $trades->offer('t3', self::member(1), self::member(3), [self::card(13)], []);

        $this->announcer->told();

        $this->clock->set('2026-11-14 12:00:00 UTC');
        self::assertSame(1, $trades->expireDue());
        self::assertSame(OfferStatus::Expired, $trades->get('t1')->status);
        self::assertSame(['OfferExpired'], $this->announcer->told());
        self::assertNull($this->offers->holder(self::card(11)));
        self::assertSame(0, $trades->expireDue());

        $this->clock->set('2026-11-15 12:00:00 UTC');
        self::assertSame('The offer "t2" has expired.', $this->closed(static fn (): Offer => $trades->accept('t2', self::member(2)))->getMessage());
        self::assertSame(['OfferExpired'], $this->announcer->told());
        self::assertSame(OfferStatus::Expired, $trades->get('t2')->status);
        self::assertSame('The offer "t3" has expired.', $this->closed(static fn (): Offer => $trades->withdraw('t3', self::member(1)))->getMessage());
        self::assertSame('The offer "t1" is expired.', $this->closed(static fn (): Offer => $trades->decline('t1', self::member(2)))->getMessage());
    }

    public function testExpiryReadsBatchesUntilAShortOne(): void
    {
        $trades = $this->trades();

        foreach ([11, 12, 13] as $card) {
            $trades->offer('t'.$card, self::member(1), self::member(2), [self::card($card)], []);
        }

        $this->clock->set('2026-11-20 00:00:00 UTC');
        self::assertSame(3, $trades->expireDue(2));
        self::assertSame(0, $trades->expireDue(1));
    }

    public function testTheGuards(): void
    {
        $trades = $this->trades(new Guards(new Allowance(2, Period::Day), 'P7D', 'UTC', new JoiningDates(['member:1' => '2026-01-01', 'member:2' => '2026-01-01', 'member:3' => '2026-11-06'])));

        self::assertSame(Refusal::SelfExchange, $this->refusal(static fn (): Offer => $trades->offer('t', self::member(1), self::member(1), [self::card(11)], []))->refusal);
        self::assertSame(Refusal::NewMember, $this->refusal(static fn (): Offer => $trades->offer('t', self::member(1), self::member(3), [self::card(11)], []))->refusal);

        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], []);
        $trades->withdraw('t1', self::member(1));
        $trades->offer('t2', self::member(1), self::member(2), [self::card(12)], []);
        self::assertSame(2, $trades->made(self::member(1)));

        $allowance = $this->refusal(static fn (): Offer => $trades->offer('t3', self::member(1), self::member(2), [self::card(13)], []));
        self::assertSame(Refusal::AllowanceReached, $allowance->refusal);
        self::assertSame('"member:1" may give 2 per day; 2 given already, 1 more is too many.', $allowance->getMessage());

        $this->clock->set('2026-11-08 00:00:00 UTC');
        self::assertSame(0, $trades->made(self::member(1)));
        self::assertSame(OfferStatus::Open, $trades->offer('t3', self::member(1), self::member(2), [self::card(13)], [])->status);
    }

    public function testTheStepsOfAnAcceptanceRunInOneTransaction(): void
    {
        $transaction = new class implements Transaction {
            public int $runs = 0;

            #[Override]
            public function run(callable $steps): mixed
            {
                ++$this->runs;

                return $steps();
            }
        };
        $trades = new Trades($this->offers, $this->inventory, $this->points, $this->currencies, $this->clock, transaction: $transaction, announcer: $this->announcer);
        $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], []);
        self::assertSame(0, $transaction->runs);

        $trades->counter('t1', self::member(2), 't2', [self::card(21)], [self::card(11)]);
        self::assertSame(1, $transaction->runs);
        $trades->accept('t2', self::member(1));
        self::assertSame(2, $transaction->runs);
    }

    public function testAnAnswerLostToAnotherOneIsRefused(): void
    {
        $trades = $this->trades();
        $offer = $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], []);
        $racing = new RacingOfferStore($this->offers, $offer);
        $lost = new Trades($racing, $this->inventory, $this->points, $this->currencies, $this->clock);

        self::assertSame('The offer "t1" was closed by another answer.', $this->closed(static fn (): Offer => $lost->accept('t1', self::member(2)))->getMessage());
        self::assertSame('The offer "t1" was closed by another answer.', $this->closed(static fn (): Offer => $lost->decline('t1', self::member(2)))->getMessage());
        self::assertSame('The offer "t1" was closed by another answer.', $this->closed(static fn (): Offer => $lost->counter('t1', self::member(2), 't2', [self::card(21)], []))->getMessage());
        self::assertSame('member:1', $this->owner(11));
    }

    public function testAnOfferOpenedByAnotherProcessFirstIsTheOneKept(): void
    {
        $now = $this->clock->now();
        $same = new Offer(ExchangeKey::fromString('t1'), self::member(1), self::member(2), [self::card(11)], [], 0, 0, 'points', $now->modify('-1 second'), $now->modify('+1 day'));
        $trades = new Trades(new LateOfferStore($same), $this->inventory, $this->points, $this->currencies, $this->clock, announcer: $this->announcer);
        self::assertSame(OfferStatus::Open, $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], [])->status);

        $other = new Offer(ExchangeKey::fromString('t1'), self::member(1), self::member(3), [self::card(12)], [], 0, 0, 'points', $now, $now->modify('+1 day'));
        $this->expectException(ExchangeConflict::class);

        new Trades(new LateOfferStore($other), $this->inventory, $this->points, $this->currencies, $this->clock)->offer('t1', self::member(1), self::member(2), [self::card(11)], []);
    }

    public function testAnUnknownOffer(): void
    {
        $this->expectException(UnknownExchange::class);
        $this->expectExceptionMessageIsOrContains('No offer is written under the key "t9".');

        $this->trades()->accept('t9', self::member(1));
    }

    public function testTheLifetimeIsADuration(): void
    {
        $trades = new Trades($this->offers, $this->inventory, $this->points, $this->currencies, $this->clock, lifetime: 'P1D');
        self::assertSame('2026-11-08 12:00:00', $trades->offer('t1', self::member(1), self::member(2), [self::card(11)], [])->expiresAt->format('Y-m-d H:i:s'));

        try {
            new Trades($this->offers, $this->inventory, $this->points, $this->currencies, $this->clock, lifetime: 'a week');
            self::fail('A refused lifetime was expected.');
        } catch (InvalidExchange $invalidExchange) {
            self::assertSame('"a week" is not an ISO 8601 duration like "P7D".', $invalidExchange->getMessage());
        }

        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('An offer waits for some time; "P0D" is no time.');

        new Trades($this->offers, $this->inventory, $this->points, $this->currencies, $this->clock, lifetime: 'P0D');
    }

    private function trades(Guards $guards = new Guards()): Trades
    {
        return new Trades($this->offers, $this->inventory, $this->points, $this->currencies, $this->clock, $guards, new ImmediateTransaction(), $this->announcer);
    }

    private function owner(int $card): ?string
    {
        return $this->inventory->ownerOf(self::card($card))?->toString();
    }

    private static function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    private static function card(int $id): ResourceReference
    {
        return ResourceReference::of('card', (string) $id);
    }

    private function refusal(callable $step): ExchangeRefused
    {
        try {
            $step();
        } catch (ExchangeRefused $exchangeRefused) {
            return $exchangeRefused;
        }

        self::fail('A refusal was expected.');
    }

    private function closed(callable $step): ExchangeClosed
    {
        try {
            $step();
        } catch (ExchangeClosed $exchangeClosed) {
            return $exchangeClosed;
        }

        self::fail('A closed offer was expected.');
    }
}
