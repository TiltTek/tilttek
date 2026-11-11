<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Trade;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Trade\ItemMove;
use Tilttek\Exchange\Trade\Offer;
use Tilttek\Exchange\Trade\OfferStatus;
use Tilttek\Points\Movement;

#[CoversClass(Offer::class)]
#[CoversClass(ItemMove::class)]
final class OfferTest extends TestCase
{
    public function testAnOfferAndItsValues(): void
    {
        $offer = self::offer();

        self::assertSame(OfferStatus::Open, $offer->status);
        self::assertSame('exchange:offer:trade:1:offered', $offer->pointsKey('offered'));
        self::assertSame([
            'key' => 'trade:1',
            'proposer' => 'member:1',
            'recipient' => 'member:2',
            'offered' => ['card:1', 'card:2'],
            'requested' => ['card:3'],
            'offered_points' => 5,
            'requested_points' => 0,
            'currency' => 'points',
            'status' => 'open',
            'made_at' => '2026-11-07T12:00:00+00:00',
            'expires_at' => '2026-11-14T12:00:00+00:00',
            'closed_at' => null,
            'counters' => null,
        ], $offer->toArray());

        self::assertSame(
            [['card:1', 'member:1', 'member:2'], ['card:2', 'member:1', 'member:2'], ['card:3', 'member:2', 'member:1']],
            array_map(static fn (ItemMove $move): array => [$move->item->toString(), $move->from->toString(), $move->to->toString()], $offer->moves()),
        );

        self::assertFalse($offer->isDue(new DateTimeImmutable('2026-11-14 11:59:59 UTC')));
        self::assertTrue($offer->isDue(new DateTimeImmutable('2026-11-14 12:00:00 UTC')));

        $closed = $offer->closed(OfferStatus::Declined, new DateTimeImmutable('2026-11-08 09:00:00 UTC'));
        self::assertFalse($closed->isDue(new DateTimeImmutable('2026-12-01 00:00:00 UTC')));
        self::assertSame('declined', $closed->toArray()['status']);
        self::assertSame('2026-11-08T09:00:00+00:00', $closed->toArray()['closed_at']);

        $counter = self::offer(counters: 'trade:0');
        self::assertSame('trade:0', $counter->toArray()['counters']);
    }

    public function testTheSameOffer(): void
    {
        $offer = self::offer();

        self::assertTrue($offer->sameAs(self::offer(madeAt: '2026-11-08 00:00:00 UTC')));
        self::assertFalse($offer->sameAs(self::offer(proposer: 3)));
        self::assertFalse($offer->sameAs(self::offer(recipient: 3)));
        self::assertFalse($offer->sameAs(self::offer(offered: ['card:1'])));
        self::assertFalse($offer->sameAs(self::offer(requested: ['card:4'])));
        self::assertFalse($offer->sameAs(self::offer(offeredPoints: 6)));
        self::assertFalse($offer->sameAs(self::offer(requestedPoints: 1)));
        self::assertFalse($offer->sameAs(self::offer(currency: 'stars')));
        self::assertFalse($offer->sameAs(self::offer(counters: 'trade:0')));
        self::assertFalse(self::offer(counters: 'trade:0')->sameAs(self::offer(counters: 'trade:9')));
        self::assertTrue(self::offer(counters: 'trade:0')->sameAs(self::offer(counters: 'trade:0')));
    }

    public function testAnErasedMemberIsReplacedOnTheirSide(): void
    {
        $erased = ResourceReference::fromString('erased:member');
        $offer = self::offer()->closed(OfferStatus::Accepted, new DateTimeImmutable('2026-11-08 00:00:00 UTC'));

        $withoutProposer = $offer->without(ResourceReference::of('member', '1'), $erased);
        self::assertSame(['erased:member', 'member:2', 'accepted', '2026-11-08T00:00:00+00:00'], [$withoutProposer->toArray()['proposer'], $withoutProposer->toArray()['recipient'], $withoutProposer->toArray()['status'], $withoutProposer->toArray()['closed_at']]);

        $withoutRecipient = $offer->without(ResourceReference::of('member', '2'), $erased);
        self::assertSame(['member:1', 'erased:member'], [$withoutRecipient->toArray()['proposer'], $withoutRecipient->toArray()['recipient']]);
        self::assertSame(['card:1', 'card:2'], $withoutRecipient->toArray()['offered']);
    }

    public function testAnOfferMayTradeOneSideOnlyAndAtTheLimits(): void
    {
        self::assertSame([], self::offer(requested: [])->requested);
        self::assertSame([], self::offer(offered: [])->offered);
        self::assertCount(50, self::offer(offered: array_map(static fn (int $card): string => 'card:'.($card + 100), range(1, 50)))->offered);
        self::assertCount(50, self::offer(requested: array_map(static fn (int $card): string => 'card:'.($card + 100), range(1, 50)))->requested);
        self::assertSame(Movement::MAX_AMOUNT, self::offer(offeredPoints: Movement::MAX_AMOUNT)->offeredPoints);
        self::assertSame(Movement::MAX_AMOUNT, self::offer(requestedPoints: Movement::MAX_AMOUNT)->requestedPoints);
    }

    /**
     * @return iterable<string, array{callable(): Offer, string}>
     */
    public static function refused(): iterable
    {
        yield 'no item' => [static fn (): Offer => self::offer(offered: [], requested: []), 'The offer "trade:1" trades no item.'];
        yield 'too many offered' => [static fn (): Offer => self::offer(offered: array_map(static fn (int $card): string => 'card:'.($card + 100), range(1, 51))), 'An offer trades at most 50 items on each side.'];
        yield 'too many requested' => [static fn (): Offer => self::offer(requested: array_map(static fn (int $card): string => 'card:'.($card + 100), range(1, 51))), 'An offer trades at most 50 items on each side.'];
        yield 'twice on a side' => [static fn (): Offer => self::offer(offered: ['card:1', 'card:1']), 'The offer "trade:1" names an item twice.'];
        yield 'on both sides' => [static fn (): Offer => self::offer(requested: ['card:1']), 'The offer "trade:1" names an item twice.'];
        yield 'negative points' => [static fn (): Offer => self::offer(offeredPoints: -1), \sprintf('An offer adds 0 to %d points on each side; "trade:1" adds -1.', Movement::MAX_AMOUNT)];
        yield 'negative requested points' => [static fn (): Offer => self::offer(requestedPoints: -1), 'adds -1.'];
        yield 'too many points' => [static fn (): Offer => self::offer(offeredPoints: Movement::MAX_AMOUNT + 1), \sprintf('adds %d.', Movement::MAX_AMOUNT + 1)];
        yield 'too many requested points' => [static fn (): Offer => self::offer(requestedPoints: Movement::MAX_AMOUNT + 1), \sprintf('adds %d.', Movement::MAX_AMOUNT + 1)];
        yield 'expired at once' => [static fn (): Offer => self::offer(expiresAt: '2026-11-07 12:00:00 UTC'), 'The offer "trade:1" expires before it is made.'];
        yield 'open and closed' => [static fn (): Offer => self::offer(closedAt: new DateTimeImmutable()), 'The offer "trade:1" is closed when, and only when, it is no longer open.'];
        yield 'closed without moment' => [static fn (): Offer => self::offer(status: OfferStatus::Expired), 'is closed when, and only when'];
    }

    /**
     * @param callable(): Offer $build
     */
    #[DataProvider('refused')]
    public function testAnOfferIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    /**
     * @param list<string> $offered
     * @param list<string> $requested
     */
    private static function offer(int $proposer = 1, int $recipient = 2, array $offered = ['card:1', 'card:2'], array $requested = ['card:3'], int $offeredPoints = 5, int $requestedPoints = 0, string $currency = 'points', string $madeAt = '2026-11-07 12:00:00 UTC', string $expiresAt = '2026-11-14 12:00:00 UTC', OfferStatus $status = OfferStatus::Open, ?DateTimeImmutable $closedAt = null, ?string $counters = null): Offer
    {
        return new Offer(
            ExchangeKey::fromString('trade:1'),
            ResourceReference::of('member', (string) $proposer),
            ResourceReference::of('member', (string) $recipient),
            array_map(ResourceReference::fromString(...), $offered),
            array_map(ResourceReference::fromString(...), $requested),
            $offeredPoints,
            $requestedPoints,
            $currency,
            new DateTimeImmutable($madeAt),
            new DateTimeImmutable($expiresAt),
            $status,
            $closedAt,
            null === $counters ? null : ExchangeKey::fromString($counters),
        );
    }
}
