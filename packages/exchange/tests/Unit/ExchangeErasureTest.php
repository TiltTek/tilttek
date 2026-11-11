<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Erasure;
use Tilttek\Exchange\ExchangeErasure;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Tests\Unit\Fixture\Clock;
use Tilttek\Exchange\Trade\InMemoryOfferStore;
use Tilttek\Exchange\Trade\Offer;
use Tilttek\Exchange\Trade\OfferStatus;
use Tilttek\Exchange\Transfer\InMemoryTransferLog;
use Tilttek\Exchange\Transfer\Transfer;

#[CoversClass(ExchangeErasure::class)]
#[CoversClass(Erasure::class)]
final class ExchangeErasureTest extends TestCase
{
    public function testAnErasedMemberLeavesTheOthersTheirExchanges(): void
    {
        $clock = new Clock('2026-11-07 12:00:00 UTC');
        $at = $clock->now();
        $reservations = new InMemoryReservationStore();
        $reservations->add(new Reservation(ExchangeKey::fromString('r1'), $this->member(1), 'seat', 1, 10, 'points', $at, $at->modify('+1 hour')), null, null);
        $reservations->add(new Reservation(ExchangeKey::fromString('r2'), $this->member(2), 'seat', 1, 10, 'points', $at, $at->modify('+1 hour')), null, null);

        $transfers = new InMemoryTransferLog();
        $transfers->record(new Transfer(ExchangeKey::fromString('t1'), $this->member(1), $this->member(2), 5, 'points', $at));

        $offers = new InMemoryOfferStore();
        $offers->open($this->offer('o1', 1, 2, 'card:1', $at));
        $offers->open($this->offer('o2', 2, 1, 'card:2', $at));
        $offers->open($this->offer('o3', 2, 3, 'card:3', $at));
        $offers->close(ExchangeKey::fromString('o2'), OfferStatus::Declined, $at);

        $clock->set('2026-11-08 12:00:00 UTC');

        $erasure = new ExchangeErasure($reservations, $clock, $transfers, $offers)->erase($this->member(1));

        self::assertSame([1, 1, 2], [$erasure->reservations, $erasure->transfers, $erasure->offers]);
        self::assertSame([], $reservations->ofMember($this->member(1)));
        self::assertCount(1, $reservations->ofMember($this->member(2)));
        self::assertSame('erased:member', $transfers->find(ExchangeKey::fromString('t1'))?->from->toString());
        self::assertSame(OfferStatus::Withdrawn, $offers->find(ExchangeKey::fromString('o1'))?->status);
        self::assertSame('2026-11-08 12:00:00', $offers->find(ExchangeKey::fromString('o1'))->closedAt?->format('Y-m-d H:i:s'));
        self::assertSame(OfferStatus::Declined, $offers->find(ExchangeKey::fromString('o2'))?->status);
        self::assertSame('2026-11-07 12:00:00', $offers->find(ExchangeKey::fromString('o2'))->closedAt?->format('Y-m-d H:i:s'));
        self::assertSame(OfferStatus::Open, $offers->find(ExchangeKey::fromString('o3'))?->status);
        self::assertNull($offers->holder(ResourceReference::fromString('card:1')));
        self::assertSame('erased:member', $offers->find(ExchangeKey::fromString('o1'))->proposer->toString());
    }

    public function testOnlyTheStoresGivenAreErased(): void
    {
        $at = new DateTimeImmutable('2026-11-07 12:00:00 UTC');
        $reservations = new InMemoryReservationStore();
        $reservations->add(new Reservation(ExchangeKey::fromString('r1'), $this->member(1), 'seat', 1, 10, 'points', $at, $at->modify('+1 hour')), null, null);

        $erasure = new ExchangeErasure($reservations, new Clock())->erase($this->member(1));

        self::assertSame([1, 0, 0], [$erasure->reservations, $erasure->transfers, $erasure->offers]);
        self::assertSame('erased:member', Erasure::erasedMember()->toString());
        self::assertSame(Erasure::ERASED, Erasure::erasedMember()->toString());
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    private function offer(string $key, int $proposer, int $recipient, string $item, DateTimeImmutable $at): Offer
    {
        return new Offer(ExchangeKey::fromString($key), $this->member($proposer), $this->member($recipient), [ResourceReference::fromString($item)], [], 0, 0, 'points', $at, $at->modify('+7 days'));
    }
}
