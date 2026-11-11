<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Reservation;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Event\ReservationCancelled;
use Tilttek\Exchange\Event\ReservationConfirmed;
use Tilttek\Exchange\Event\ReservationExpired;
use Tilttek\Exchange\Event\RewardReserved;
use Tilttek\Exchange\Exception\ExchangeClosed;
use Tilttek\Exchange\Exception\ExchangeConflict;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\Exception\UnknownExchange;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Redemptions;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Reservation\ReservationStatus;
use Tilttek\Exchange\Reward\InMemoryCatalog;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\Exchange\SilentAnnouncer;
use Tilttek\Exchange\Tests\Unit\Fixture\Clock;
use Tilttek\Exchange\Tests\Unit\Fixture\LateReservationStore;
use Tilttek\Exchange\Tests\Unit\Fixture\MetRequirements;
use Tilttek\Exchange\Tests\Unit\Fixture\RecordingAnnouncer;
use Tilttek\Exchange\Tests\Unit\Fixture\SettledElsewhereStore;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;

#[CoversClass(Redemptions::class)]
#[CoversClass(InMemoryReservationStore::class)]
#[CoversClass(RewardReserved::class)]
#[CoversClass(ReservationConfirmed::class)]
#[CoversClass(ReservationCancelled::class)]
#[CoversClass(ReservationExpired::class)]
#[CoversClass(SilentAnnouncer::class)]
#[CoversClass(UnknownExchange::class)]
#[CoversClass(ExchangeClosed::class)]
#[CoversClass(ExchangeConflict::class)]
final class RedemptionsTest extends TestCase
{
    private Clock $clock;

    private InMemoryCatalog $catalog;

    private InMemoryReservationStore $reservations;

    private Points $points;

    private RecordingAnnouncer $announcer;

    private Redemptions $redemptions;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new Clock('2026-11-07 12:00:00 UTC');
        $this->catalog = new InMemoryCatalog(
            new Reward('discount-10', 100),
            new Reward('seat', 250, stock: 2, perMember: 1),
            new Reward('sticker', 5, 'stars'),
            new Reward('vip', 10, requires: ['badge:helper', 'level:5']),
            new Reward('winter', 10, availableFrom: new DateTimeImmutable('2026-12-01 00:00:00 UTC')),
            new Reward('autumn', 10, availableUntil: new DateTimeImmutable('2026-11-01 00:00:00 UTC')),
            new Reward('winter-vip', 10, stock: 0, availableFrom: new DateTimeImmutable('2026-12-01 00:00:00 UTC'), requires: ['level:9']),
        );
        $this->reservations = new InMemoryReservationStore();
        $this->points = new Points(new InMemoryLedger(), $this->clock, new Currencies(new Currency('points'), new Currency('stars')));
        $this->announcer = new RecordingAnnouncer();
        $this->redemptions = new Redemptions(
            $this->catalog,
            $this->reservations,
            $this->points,
            new Currencies(new Currency('points'), new Currency('stars')),
            $this->clock,
            new MetRequirements(['member:1' => ['badge:helper', 'level:5'], 'member:2' => ['badge:helper']]),
            $this->announcer,
        );

        foreach ([1, 2, 3] as $member) {
            $this->points->grant($this->member($member), 1000, 'welcome:'.$member);
            $this->points->grant($this->member($member), 10, 'welcome-stars:'.$member, currency: 'stars');
        }
    }

    public function testAReservationSpendsThePointsAndHoldsTheUnits(): void
    {
        $reservation = $this->redemptions->reserve($this->member(1), 'discount-10', 'order:1', 3);

        self::assertSame('order:1', $reservation->key->value);
        self::assertTrue($reservation->member->equals($this->member(1)));
        self::assertSame('discount-10', $reservation->reward);
        self::assertSame(3, $reservation->quantity);
        self::assertSame(300, $reservation->amount);
        self::assertSame('points', $reservation->currency);
        self::assertSame(ReservationStatus::Reserved, $reservation->status);
        self::assertSame('2026-11-07 12:00:00', $reservation->reservedAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-07 12:30:00', $reservation->expiresAt->format('Y-m-d H:i:s'));
        self::assertSame(700, $this->points->balance($this->member(1)));
        self::assertSame(['RewardReserved'], $this->announcer->told());
        self::assertSame($reservation, $this->redemptions->get('order:1'));

        // The same key reserves once.
        self::assertEquals($reservation, $this->redemptions->reserve($this->member(1), 'discount-10', 'order:1', 3));
        self::assertSame(700, $this->points->balance($this->member(1)));
        self::assertSame([], $this->announcer->told());

        $stars = $this->redemptions->reserve($this->member(1), 'sticker', 'order:2');
        self::assertSame('stars', $stars->currency);
        self::assertSame(5, $this->points->balance($this->member(1), 'stars'));
        self::assertSame(['order:1', 'order:2'], array_map(static fn (Reservation $reservation): string => $reservation->key->value, $this->redemptions->ofMember($this->member(1))));
    }

    public function testTheSameKeyForAnotherReservationIsAConflict(): void
    {
        $this->redemptions->reserve($this->member(1), 'discount-10', 'order:1');

        foreach ([[2, 'discount-10', 1], [1, 'sticker', 1], [1, 'discount-10', 2]] as [$member, $reward, $quantity]) {
            try {
                $this->redemptions->reserve($this->member($member), $reward, 'order:1', $quantity);
                self::fail('A conflict was expected.');
            } catch (ExchangeConflict $conflict) {
                self::assertSame('The key "order:1" is already written for another reservation.', $conflict->getMessage());
            }
        }

        self::assertSame(900, $this->points->balance($this->member(1)));
    }

    public function testAReservationThePointsCannotPayHoldsNothing(): void
    {
        try {
            $this->redemptions->reserve($this->member(1), 'seat', 'order:1', 2);
            self::fail('The member limit was expected.');
        } catch (ExchangeRefused $exchangeRefused) {
            self::assertSame(Refusal::MemberLimitReached, $exchangeRefused->refusal);
        }

        $this->points->spend($this->member(3), 900, 'elsewhere', 'elsewhere');

        try {
            $this->redemptions->reserve($this->member(3), 'seat', 'order:3');
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints) {
            self::assertNull($this->reservations->find(ExchangeKey::fromString('order:3')));
            self::assertSame(2, $this->redemptions->remaining('seat'));
            self::assertSame([], $this->announcer->told());
        }

        $this->points->grant($this->member(3), 150, 'later');
        self::assertSame(ReservationStatus::Reserved, $this->redemptions->reserve($this->member(3), 'seat', 'order:3')->status);
        self::assertSame(1, $this->redemptions->remaining('seat'));
    }

    public function testAReservationInterruptedBeforeItsSpendingIsPaidWhenAskedAgain(): void
    {
        $now = $this->clock->now();
        $this->reservations->add(new Reservation(ExchangeKey::fromString('order:1'), $this->member(1), 'discount-10', 1, 100, 'points', $now, $now->modify('+30 minutes')), null, null);

        $this->redemptions->reserve($this->member(1), 'discount-10', 'order:1');
        self::assertSame(900, $this->points->balance($this->member(1)));
        self::assertSame(['RewardReserved'], $this->announcer->told());

        // Its spending missing, a cancelled reservation gives nothing back.
        $this->reservations->add(new Reservation(ExchangeKey::fromString('order:2'), $this->member(1), 'discount-10', 1, 100, 'points', $now, $now->modify('+30 minutes')), null, null);
        self::assertSame(ReservationStatus::Cancelled, $this->redemptions->cancel('order:2')->status);
        self::assertSame(900, $this->points->balance($this->member(1)));
        self::assertSame(['ReservationCancelled'], $this->announcer->told());

        // A settled reservation asked again is returned as it is.
        self::assertSame(ReservationStatus::Cancelled, $this->redemptions->reserve($this->member(1), 'discount-10', 'order:2')->status);
        self::assertSame(900, $this->points->balance($this->member(1)));
    }

    public function testTheRewardsRules(): void
    {
        $this->clock->set('2026-11-07 12:00:00 UTC');

        self::assertSame(Refusal::NotYetAvailable, $this->refusal(fn (): Reservation => $this->redemptions->reserve($this->member(1), 'winter', 'w'))->refusal);
        self::assertSame('The reward "winter" is not available at this moment.', $this->refusal(fn (): Reservation => $this->redemptions->reserve($this->member(1), 'winter', 'w'))->getMessage());
        self::assertSame(Refusal::NoLongerAvailable, $this->refusal(fn (): Reservation => $this->redemptions->reserve($this->member(1), 'autumn', 'a'))->refusal);

        $unmet = $this->refusal(fn (): Reservation => $this->redemptions->reserve($this->member(2), 'vip', 'v'));
        self::assertSame(Refusal::RequirementUnmet, $unmet->refusal);
        self::assertSame('"member:2" does not meet "level:5", which the reward "vip" requires.', $unmet->getMessage());
        self::assertSame('"member:3" does not meet "badge:helper", which the reward "vip" requires.', $this->refusal(fn (): Reservation => $this->redemptions->reserve($this->member(3), 'vip', 'v'))->getMessage());
        self::assertSame(ReservationStatus::Reserved, $this->redemptions->reserve($this->member(1), 'vip', 'v')->status);

        $this->redemptions->reserve($this->member(1), 'seat', 's1');
        $this->redemptions->reserve($this->member(2), 'seat', 's2');
        self::assertSame(0, $this->redemptions->remaining('seat'));
        self::assertSame(Refusal::OutOfStock, $this->refusal(fn (): Reservation => $this->redemptions->reserve($this->member(3), 'seat', 's3'))->refusal);
        self::assertNull($this->redemptions->remaining('discount-10'));
        self::assertSame(1000, $this->points->balance($this->member(3)));
        self::assertSame([], $this->reservations->ofMember($this->member(3)));
    }

    public function testWhyAMemberCannotReserve(): void
    {
        self::assertNull($this->redemptions->refusal($this->member(1), 'discount-10', 50));
        self::assertSame(Refusal::NotYetAvailable, $this->redemptions->refusal($this->member(1), 'winter'));
        self::assertSame(Refusal::NoLongerAvailable, $this->redemptions->refusal($this->member(1), 'autumn'));
        self::assertSame(Refusal::NotYetAvailable, $this->redemptions->refusal($this->member(3), 'winter-vip'));
        self::assertSame(Refusal::RequirementUnmet, $this->redemptions->refusal($this->member(2), 'vip'));
        self::assertNull($this->redemptions->refusal($this->member(1), 'vip'));
        self::assertNull($this->redemptions->refusal($this->member(1), 'seat'));
        self::assertSame(Refusal::MemberLimitReached, $this->redemptions->refusal($this->member(1), 'seat', 2));

        $this->redemptions->reserve($this->member(1), 'seat', 's1');
        self::assertSame(Refusal::MemberLimitReached, $this->redemptions->refusal($this->member(1), 'seat'));
        self::assertNull($this->redemptions->refusal($this->member(2), 'seat'));
        self::assertSame(Refusal::OutOfStock, $this->redemptions->refusal($this->member(2), 'seat', 2));
        $this->redemptions->reserve($this->member(2), 'seat', 's2');
        self::assertSame(Refusal::OutOfStock, $this->redemptions->refusal($this->member(3), 'seat'));

        // A stock lowered below the units held leaves none.
        $this->catalog->save(new Reward('seat', 250, stock: 1));
        self::assertSame(0, $this->redemptions->remaining('seat'));
    }

    public function testAReservationAskedAgainNeedsNoLongerItsReward(): void
    {
        $reservation = $this->redemptions->reserve($this->member(1), 'discount-10', 'order:1');
        $this->catalog->remove('discount-10');

        self::assertEquals($reservation, $this->redemptions->reserve($this->member(1), 'discount-10', 'order:1'));
        self::assertSame(900, $this->points->balance($this->member(1)));
    }

    public function testAReservationWrittenByAnotherProcessFirstIsTheOneKept(): void
    {
        $now = $this->clock->now();
        $competitor = new Reservation(ExchangeKey::fromString('order:1'), $this->member(1), 'discount-10', 1, 90, 'points', $now->modify('-1 second'), $now->modify('+1 hour'));
        $redemptions = new Redemptions($this->catalog, new LateReservationStore($competitor), $this->points, new Currencies(new Currency('points')), $this->clock, announcer: $this->announcer);

        self::assertSame($competitor, $redemptions->reserve($this->member(1), 'discount-10', 'order:1'));
        self::assertSame(910, $this->points->balance($this->member(1)));
        self::assertSame(['RewardReserved'], $this->announcer->told());

        $other = new Reservation(ExchangeKey::fromString('order:2'), $this->member(2), 'discount-10', 1, 100, 'points', $now, $now->modify('+1 hour'));
        $this->expectException(ExchangeConflict::class);

        new Redemptions($this->catalog, new LateReservationStore($other), $this->points, new Currencies(new Currency('points')), $this->clock)->reserve($this->member(1), 'discount-10', 'order:2');
    }

    public function testARefusalAsksForAQuantity(): void
    {
        $this->expectException(InvalidExchange::class);

        $this->redemptions->refusal($this->member(1), 'seat', 0);
    }

    public function testConfirmingKeepsThePointsSpent(): void
    {
        $this->redemptions->reserve($this->member(1), 'seat', 'order:1');
        $this->announcer->told();
        $this->clock->set('2026-11-07 12:29:59 UTC');

        $confirmed = $this->redemptions->confirm('order:1');
        self::assertSame(ReservationStatus::Confirmed, $confirmed->status);
        self::assertSame('2026-11-07 12:29:59', $confirmed->settledAt?->format('Y-m-d H:i:s'));
        self::assertSame(750, $this->points->balance($this->member(1)));
        self::assertSame(['ReservationConfirmed'], $this->announcer->told());
        self::assertSame(1, $this->redemptions->remaining('seat'));

        $this->clock->set('2026-11-08 12:00:00 UTC');
        self::assertEquals($confirmed, $this->redemptions->confirm('order:1'));
        self::assertSame([], $this->announcer->told());
        self::assertSame(0, $this->redemptions->expireDue());

        $closed = $this->closed(fn (): Reservation => $this->redemptions->cancel('order:1'));
        self::assertSame('The reservation "order:1" is confirmed; it cannot be cancelled.', $closed->getMessage());
        self::assertSame(750, $this->points->balance($this->member(1)));
    }

    public function testCancellingGivesThePointsBack(): void
    {
        $this->redemptions->reserve($this->member(1), 'seat', 'order:1');
        $this->announcer->told();

        $cancelled = $this->redemptions->cancel('order:1');
        self::assertSame(ReservationStatus::Cancelled, $cancelled->status);
        self::assertSame(1000, $this->points->balance($this->member(1)));
        self::assertSame(2, $this->redemptions->remaining('seat'));
        self::assertSame(['ReservationCancelled'], $this->announcer->told());

        self::assertEquals($cancelled, $this->redemptions->cancel('order:1'));
        self::assertSame(1000, $this->points->balance($this->member(1)));
        self::assertSame([], $this->announcer->told());
        self::assertSame('The reservation "order:1" is cancelled; it cannot be confirmed.', $this->closed(fn (): Reservation => $this->redemptions->confirm('order:1'))->getMessage());
    }

    public function testAnUnconfirmedReservationExpires(): void
    {
        $this->redemptions->reserve($this->member(1), 'seat', 'order:1');
        $this->clock->set('2026-11-07 12:10:00 UTC');
        $this->redemptions->reserve($this->member(2), 'seat', 'order:2');
        $this->redemptions->reserve($this->member(3), 'discount-10', 'order:3');

        $this->announcer->told();

        $this->clock->set('2026-11-07 12:30:00 UTC');
        self::assertSame(1, $this->redemptions->expireDue());
        self::assertSame(1000, $this->points->balance($this->member(1)));
        self::assertSame(ReservationStatus::Expired, $this->redemptions->get('order:1')->status);
        self::assertSame(['ReservationExpired'], $this->announcer->told());
        self::assertSame(0, $this->redemptions->expireDue());

        // A confirmation past the lifetime expires the reservation first.
        $this->clock->set('2026-11-07 12:40:00 UTC');
        self::assertSame('The reservation "order:2" is expired; it cannot be confirmed.', $this->closed(fn (): Reservation => $this->redemptions->confirm('order:2'))->getMessage());
        self::assertSame(1000, $this->points->balance($this->member(2)));
        self::assertSame(['ReservationExpired'], $this->announcer->told());
        self::assertSame('The reservation "order:1" is expired; it cannot be cancelled.', $this->closed(fn (): Reservation => $this->redemptions->cancel('order:1'))->getMessage());

        // Every batch is read until a short one.
        self::assertSame(1, $this->redemptions->expireDue(1));
    }

    public function testExpiryReadsBatchesUntilAShortOne(): void
    {
        foreach (range(1, 5) as $order) {
            $this->redemptions->reserve($this->member(1), 'discount-10', 'order:'.$order);
        }

        $this->clock->set('2026-11-07 13:00:00 UTC');
        self::assertSame(5, $this->redemptions->expireDue(2));
        self::assertSame(1000, $this->points->balance($this->member(1)));
    }

    public function testAReservationExpiredByAnotherProcessIsNotCounted(): void
    {
        $now = $this->clock->now();
        $due = new Reservation(ExchangeKey::fromString('order:1'), $this->member(1), 'discount-10', 1, 100, 'points', $now->modify('-1 hour'), $now);
        $redemptions = new Redemptions($this->catalog, new SettledElsewhereStore($due), $this->points, new Currencies(new Currency('points')), $this->clock, announcer: $this->announcer);

        self::assertSame(0, $redemptions->expireDue(2));
        self::assertSame([], $this->announcer->told());
    }

    public function testAnUnknownReservation(): void
    {
        $this->expectException(UnknownExchange::class);
        $this->expectExceptionMessageIsOrContains('No reservation is written under the key "order:9".');

        $this->redemptions->confirm('order:9');
    }

    public function testTheLifetimeIsADuration(): void
    {
        $redemptions = new Redemptions($this->catalog, $this->reservations, $this->points, new Currencies(new Currency('points')), $this->clock, lifetime: 'P2D');
        self::assertSame('2026-11-09 12:00:00', $redemptions->reserve($this->member(1), 'discount-10', 'order:1')->expiresAt->format('Y-m-d H:i:s'));

        try {
            new Redemptions($this->catalog, $this->reservations, $this->points, new Currencies(new Currency('points')), $this->clock, lifetime: 'half an hour');
            self::fail('A refused lifetime was expected.');
        } catch (InvalidExchange $invalidExchange) {
            self::assertSame('"half an hour" is not an ISO 8601 duration like "PT30M".', $invalidExchange->getMessage());
        }

        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('A reservation waits for some time; "PT0S" is no time.');

        new Redemptions($this->catalog, $this->reservations, $this->points, new Currencies(new Currency('points')), $this->clock, lifetime: 'PT0S');
    }

    public function testWithoutRequirementsAConditionIsNeverMet(): void
    {
        $redemptions = new Redemptions($this->catalog, $this->reservations, $this->points, new Currencies(new Currency('points')), $this->clock);

        self::assertSame(Refusal::RequirementUnmet, $redemptions->refusal($this->member(1), 'vip'));
        self::assertSame(ReservationStatus::Reserved, $redemptions->reserve($this->member(1), 'discount-10', 'order:1')->status);
        self::assertSame(ReservationStatus::Cancelled, $redemptions->cancel('order:1')->status);
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    private function refusal(callable $reserve): ExchangeRefused
    {
        try {
            $reserve();
        } catch (ExchangeRefused $exchangeRefused) {
            return $exchangeRefused;
        }

        self::fail('A refusal was expected.');
    }

    private function closed(callable $settle): ExchangeClosed
    {
        try {
            $settle();
        } catch (ExchangeClosed $exchangeClosed) {
            return $exchangeClosed;
        }

        self::fail('A closed reservation was expected.');
    }
}
