<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Trade;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Trade\Offer;
use Tilttek\Exchange\Trade\OfferStatus;
use Tilttek\Exchange\Trade\OfferStore;

/**
 * The contract every store of offers passes: a store of the application's storage extends this case and builds itself
 * in `store()`.
 */
abstract class OfferStoreTestCase extends TestCase
{
    abstract protected function store(): OfferStore;

    public function testAnOfferIsOpenedOnceByItsKeyAndHoldsItsOfferedItems(): void
    {
        $store = $this->store();

        self::assertTrue($store->open(self::offer('a', 1, 2, ['card:1', 'card:2'], ['card:3'], counters: 'z')));
        self::assertFalse($store->open(self::offer('a', 3, 4, ['card:9'], [])));
        self::assertSame(self::offer('a', 1, 2, ['card:1', 'card:2'], ['card:3'], counters: 'z')->toArray(), $this->utc($store->find(ExchangeKey::fromString('a'))));
        self::assertNull($store->find(ExchangeKey::fromString('b')));
        self::assertSame('a', $store->holder(self::item(1))?->value);
        self::assertSame('a', $store->holder(self::item(2))?->value);
        self::assertNull($store->holder(self::item(3)));
        self::assertNull($store->holder(self::item(9)));

        $held = $this->refusal(static fn (): bool => $store->open(self::offer('b', 1, 3, ['card:4', 'card:2'], [])));
        self::assertSame(Refusal::AlreadyHeld, $held->refusal);
        self::assertSame('"card:2" is held by the offer "a".', $held->getMessage());
        self::assertNull($store->find(ExchangeKey::fromString('b')));
        self::assertNull($store->holder(self::item(4)));

        // An item only requested is not held.
        self::assertTrue($store->open(self::offer('c', 4, 1, ['card:5'], ['card:1'], requestedPoints: 3)));
        self::assertSame(3, $store->find(ExchangeKey::fromString('c'))?->requestedPoints);
    }

    public function testAnOfferLeavesOpenOnceAndFreesItsItems(): void
    {
        $store = $this->store();
        $store->open(self::offer('a', 1, 2, ['card:1'], ['card:3']));

        self::assertFalse($store->close(ExchangeKey::fromString('b'), OfferStatus::Declined, self::at('2026-11-08 09:00')));
        self::assertTrue($store->close(ExchangeKey::fromString('a'), OfferStatus::Declined, self::at('2026-11-08 09:00')));
        self::assertFalse($store->close(ExchangeKey::fromString('a'), OfferStatus::Accepted, self::at('2026-11-08 09:01')));

        $closed = $store->find(ExchangeKey::fromString('a'));
        self::assertSame(OfferStatus::Declined, $closed?->status);
        self::assertSame('2026-11-08 09:00:00', $closed->closedAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        self::assertNull($store->holder(self::item(1)));
        self::assertTrue($store->open(self::offer('b', 1, 3, ['card:1'], [])));
        self::assertSame('b', $store->holder(self::item(1))?->value);
    }

    public function testTheOffersAMemberMadeOverAPeriod(): void
    {
        $store = $this->store();
        $store->open(self::offer('a', 1, 2, ['card:1'], [], '2026-11-07 11:59'));
        $store->open(self::offer('b', 1, 2, ['card:2'], [], '2026-11-07 12:00'));
        $store->open(self::offer('c', 1, 3, ['card:3'], [], '2026-11-07 13:00'));
        $store->open(self::offer('d', 2, 1, ['card:4'], [], '2026-11-07 12:30'));
        $store->close(ExchangeKey::fromString('b'), OfferStatus::Withdrawn, self::at('2026-11-07 12:10'));

        self::assertSame(3, $store->made(self::member(1), null, null));
        self::assertSame(1, $store->made(self::member(1), self::at('2026-11-07 12:00'), self::at('2026-11-07 13:00')));
        self::assertSame(2, $store->made(self::member(1), self::at('2026-11-07 12:00'), null));
        self::assertSame(2, $store->made(self::member(1), null, self::at('2026-11-07 13:00')));
        self::assertSame(1, $store->made(self::member(2), null, null));
        self::assertSame(0, $store->made(self::member(3), null, null));
    }

    public function testTheDueOffersComeOldestFirst(): void
    {
        $store = $this->store();
        $store->open(self::offer('c', 1, 2, ['card:1'], [], expiresAt: '2026-11-08 12:20'));
        $store->open(self::offer('b', 1, 2, ['card:2'], [], expiresAt: '2026-11-08 12:10'));
        $store->open(self::offer('a', 1, 2, ['card:3'], [], expiresAt: '2026-11-08 12:20'));
        $store->open(self::offer('d', 1, 2, ['card:4'], [], expiresAt: '2026-11-08 12:30'));
        $store->open(self::offer('e', 1, 2, ['card:5'], [], expiresAt: '2026-11-08 12:05'));
        $store->close(ExchangeKey::fromString('e'), OfferStatus::Withdrawn, self::at('2026-11-08 12:00'));

        self::assertSame(['b', 'a', 'c'], $this->keys($store->due(self::at('2026-11-08 12:20'), 10)));
        self::assertSame(['b', 'a'], $this->keys($store->due(self::at('2026-11-08 12:20'), 2)));
        self::assertSame([], $store->due(self::at('2026-11-08 12:09'), 10));
    }

    public function testAMembersOffersAndTheirErasure(): void
    {
        $store = $this->store();
        $store->open(self::offer('a', 1, 2, ['card:1'], [], '2026-11-07 12:00'));
        $store->open(self::offer('b', 3, 1, ['card:2'], [], '2026-11-07 12:01'));
        $store->open(self::offer('c', 2, 3, ['card:3'], [], '2026-11-07 12:02'));
        $store->close(ExchangeKey::fromString('a'), OfferStatus::Accepted, self::at('2026-11-07 13:00'));
        $store->close(ExchangeKey::fromString('b'), OfferStatus::Withdrawn, self::at('2026-11-07 13:00'));

        self::assertSame(['a', 'b'], $this->keys($store->ofMember(self::member(1))));
        self::assertSame(['a', 'c'], $this->keys($store->ofMember(self::member(2))));
        self::assertSame([], $store->ofMember(self::member(4)));

        self::assertSame(2, $store->eraseMember(self::member(1)));
        self::assertSame(0, $store->eraseMember(self::member(1)));
        self::assertSame([], $store->ofMember(self::member(1)));
        self::assertSame('erased:member', $store->find(ExchangeKey::fromString('a'))?->proposer->toString());
        self::assertSame('member:2', $store->find(ExchangeKey::fromString('a'))->recipient->toString());
        self::assertSame('erased:member', $store->find(ExchangeKey::fromString('b'))?->recipient->toString());
        self::assertSame(OfferStatus::Accepted, $store->find(ExchangeKey::fromString('a'))->status);
        self::assertSame(['a', 'c'], $this->keys($store->ofMember(self::member(2))));
        self::assertSame('c', $store->holder(self::item(3))?->value);
    }

    /**
     * @param list<string> $offered
     * @param list<string> $requested
     */
    protected static function offer(string $key, int $proposer, int $recipient, array $offered, array $requested, string $madeAt = '2026-11-07 12:00', string $expiresAt = '2026-11-14 12:00', int $offeredPoints = 0, int $requestedPoints = 0, ?string $counters = null): Offer
    {
        return new Offer(
            ExchangeKey::fromString($key),
            self::member($proposer),
            self::member($recipient),
            array_map(ResourceReference::fromString(...), $offered),
            array_map(ResourceReference::fromString(...), $requested),
            $offeredPoints,
            $requestedPoints,
            'points',
            self::at($madeAt),
            self::at($expiresAt),
            counters: null === $counters ? null : ExchangeKey::fromString($counters),
        );
    }

    protected static function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    protected static function item(int $id): ResourceReference
    {
        return ResourceReference::of('card', (string) $id);
    }

    protected static function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.':00 UTC');
    }

    /**
     * @return array<string, mixed>
     */
    private function utc(?Offer $offer): array
    {
        self::assertInstanceOf(Offer::class, $offer);
        $utc = new DateTimeZone('UTC');

        return new Offer($offer->key, $offer->proposer, $offer->recipient, $offer->offered, $offer->requested, $offer->offeredPoints, $offer->requestedPoints, $offer->currency, $offer->madeAt->setTimezone($utc), $offer->expiresAt->setTimezone($utc), $offer->status, $offer->closedAt?->setTimezone($utc), $offer->counters)->toArray();
    }

    /**
     * @param list<Offer> $offers
     *
     * @return list<string>
     */
    private function keys(array $offers): array
    {
        return array_map(static fn (Offer $offer): string => $offer->key->value, $offers);
    }

    private function refusal(callable $open): ExchangeRefused
    {
        try {
            $open();
        } catch (ExchangeRefused $exchangeRefused) {
            return $exchangeRefused;
        }

        self::fail('A refusal was expected.');
    }
}
