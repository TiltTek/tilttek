<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\Want;
use Tilttek\MathTrade\WantGroup;

#[CoversClass(Offer::class)]
#[CoversClass(Participant::class)]
final class OfferTest extends TestCase
{
    public function testKeepsItsIdentifierWishesAndGame(): void
    {
        $wants = [new Want('b1'), new WantGroup(['c1', 'c2'])];
        $offer = new Offer('a1', $wants, 'azul');

        self::assertSame('a1', $offer->id);
        self::assertSame($wants, $offer->wants);
        self::assertSame('azul', $offer->game);
    }

    public function testHasNoWishAndNoGameByDefault(): void
    {
        $offer = new Offer('a1');

        self::assertSame([], $offer->wants);
        self::assertNull($offer->game);
    }

    public function testGivesThePriorityOfAWishedOffer(): void
    {
        $offer = new Offer('a1', [new Want('b1'), new WantGroup(['c1', 'c2']), new Want('d1')]);

        self::assertSame(1, $offer->priorityOf('b1'));
        self::assertSame(2, $offer->priorityOf('c1'));
        self::assertSame(2, $offer->priorityOf('c2'));
        self::assertSame(3, $offer->priorityOf('d1'));
        self::assertNull($offer->priorityOf('e1'));
    }

    public function testComparesIdentifiersStrictly(): void
    {
        self::assertNull(new Offer('a1', [new Want('10')])->priorityOf('1e1'));
    }

    public function testRefusesAnInvalidIdentifier(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The identifier of an offer');

        new Offer('');
    }

    public function testRefusesAnInvalidGame(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a game');

        new Offer('a1', [], '');
    }

    public function testRefusesToWishForItself(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The offer "a1" wishes for itself.');

        new Offer('a1', [new Want('b1'), new WantGroup(['c1', 'a1'])]);
    }

    public function testRefusesAnOfferWishedTwice(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The offer "a1" wishes for the offer "c1" twice.');

        new Offer('a1', [new Want('c1'), new WantGroup(['b1', 'c1'])]);
    }

    public function testAParticipantKeepsItsOffers(): void
    {
        $offers = [new Offer('a1'), new Offer('a2')];
        $participant = new Participant('alice', $offers);

        self::assertSame('alice', $participant->id);
        self::assertSame($offers, $participant->offers);
    }

    public function testAParticipantMakesAnOffer(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The participant "alice" makes no offer.');

        new Participant('alice', []);
    }

    public function testAParticipantRefusesAnInvalidIdentifier(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a participant');

        new Participant("\0", [new Offer('a1')]);
    }
}
