<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\ExchangeErasure;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Trade\InMemoryOfferStore;
use Tilttek\Exchange\Trade\Offer;
use Tilttek\Exchange\Transfer\InMemoryTransferLog;
use Tilttek\Exchange\Transfer\Transfer;
use Tilttek\ExchangeBundle\Infrastructure\Privacy\ExchangePrivacy;
use Tilttek\ExchangeBundle\Tests\Unit\Fixture\Clock;

#[CoversClass(ExchangePrivacy::class)]
final class ExchangePrivacyTest extends TestCase
{
    public function testAMembersExchangesAreExportedAndErased(): void
    {
        $at = new DateTimeImmutable('2026-11-07 12:00:00 UTC');
        $reservations = new InMemoryReservationStore();
        $reservations->add(new Reservation(ExchangeKey::fromString('r1'), $this->member('44'), 'seat', 1, 10, 'points', $at, $at->modify('+1 hour')), null, null);

        $transfers = new InMemoryTransferLog();
        $transfers->record(new Transfer(ExchangeKey::fromString('t1'), $this->member('44'), $this->member('45'), 5, 'points', $at));

        $offers = new InMemoryOfferStore();
        $offers->open(new Offer(ExchangeKey::fromString('o1'), $this->member('45'), $this->member('44'), [ResourceReference::fromString('card:1')], [], 0, 0, 'points', $at, $at->modify('+7 days')));

        $clock = new Clock();
        $privacy = new ExchangePrivacy(new ExchangeErasure($reservations, $clock, $transfers, $offers), $reservations, 'user', $transfers, $offers);

        self::assertSame('tilttek_exchange', $privacy->module()->value);

        $export = $privacy->export(DataSubject::fromString('44'));
        self::assertSame(['reservations', 'transfers', 'offers'], array_keys($export));
        self::assertSame('r1', $export['reservations'][0]['key'] ?? null);
        self::assertSame('t1', $export['transfers'][0]['key'] ?? null);
        self::assertSame('o1', $export['offers'][0]['key'] ?? null);
        self::assertSame([], $privacy->export(DataSubject::fromString('99')));

        $report = $privacy->erase(DataSubject::fromString('44'));
        self::assertSame('tilttek_exchange', $report->module->value);
        self::assertSame([
            'tilttek_exchange_reservation' => ErasureEffect::Deleted,
            'tilttek_exchange_transfer.from_member' => ErasureEffect::Redacted,
            'tilttek_exchange_transfer.to_member' => ErasureEffect::Redacted,
            'tilttek_exchange_offer.proposer' => ErasureEffect::Redacted,
            'tilttek_exchange_offer.recipient' => ErasureEffect::Redacted,
        ], $report->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('44')));
        self::assertSame('erased:member', $transfers->find(ExchangeKey::fromString('t1'))?->from->toString());
    }

    public function testOnlyTheReservationsWithoutTransfersOrTrades(): void
    {
        $at = new DateTimeImmutable('2026-11-07 12:00:00 UTC');
        $reservations = new InMemoryReservationStore();
        $reservations->add(new Reservation(ExchangeKey::fromString('r1'), $this->member('44'), 'seat', 1, 10, 'points', $at, $at->modify('+1 hour')), null, null);

        $privacy = new ExchangePrivacy(new ExchangeErasure($reservations, new Clock()), $reservations, 'user');

        self::assertSame(['reservations'], array_keys($privacy->export(DataSubject::fromString('44'))));
        self::assertSame(['tilttek_exchange_reservation' => ErasureEffect::Deleted], $privacy->erase(DataSubject::fromString('44'))->effects);
        self::assertSame([], $reservations->ofMember($this->member('44')));
    }

    private function member(string $id): ResourceReference
    {
        return ResourceReference::of('user', $id);
    }
}
