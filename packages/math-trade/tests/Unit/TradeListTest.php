<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Exception\LimitExceeded;
use Tilttek\MathTrade\Exception\MathTradeException;
use Tilttek\MathTrade\Limits;
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\Tests\Unit\Fixture\Lists;
use Tilttek\MathTrade\TradeList;

#[CoversClass(TradeList::class)]
#[CoversClass(InvalidTradeList::class)]
#[CoversClass(LimitExceeded::class)]
final class TradeListTest extends TestCase
{
    public function testKeepsItsParticipantsAndLimits(): void
    {
        $limits = new Limits(participants: 10);
        $participants = [new Participant('alice', [new Offer('a1')]), new Participant('bob', [new Offer('b1')])];
        $list = new TradeList($participants, $limits);

        self::assertSame($participants, $list->participants);
        self::assertSame($limits, $list->limits);
    }

    public function testListsTheOffersInTheOrderOfTheParticipants(): void
    {
        $list = Lists::of(['bob' => ['b2' => [], 'b1' => []], 'alice' => ['a1' => ['b1']]]);

        self::assertSame(['b2', 'b1', 'a1'], array_map(static fn (Offer $offer): string => $offer->id, $list->offers()));
        self::assertSame('b1', $list->offer('b1')->id);
        self::assertSame('alice', $list->ownerOf('a1'));
        self::assertSame('bob', $list->ownerOf('b2'));
    }

    public function testReadsIdentifiersMadeOfDigits(): void
    {
        $list = Lists::of(['1' => ['10' => ['20']], '2' => ['20' => ['10']]]);

        self::assertSame(['10', '20'], array_map(static fn (Offer $offer): string => $offer->id, $list->offers()));
        self::assertSame('2', $list->ownerOf('20'));
    }

    public function testMayBeEmpty(): void
    {
        self::assertSame([], new TradeList([])->offers());
    }

    public function testRefusesAnUnknownOffer(): void
    {
        $list = Lists::of(['alice' => ['a1' => []]]);

        try {
            $list->offer('x');
            self::fail('An unknown offer was found.');
        } catch (InvalidTradeList $invalidTradeList) {
            self::assertSame('Nobody offers "x".', $invalidTradeList->getMessage());
        }

        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('Nobody offers "y".');

        $list->ownerOf('y');
    }

    public function testRefusesTwoParticipantsWithOneIdentifier(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('Two participants are identified by "alice".');

        new TradeList([new Participant('alice', [new Offer('a1')]), new Participant('alice', [new Offer('a2')])]);
    }

    public function testRefusesTwoOffersWithOneIdentifier(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('Two offers are identified by "x1".');

        Lists::of(['alice' => ['x1' => []], 'bob' => ['x1' => []]]);
    }

    public function testRefusesAWishForAnOfferNobodyMakes(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The offer "a1" wishes for "z9", which nobody offers.');

        Lists::of(['alice' => ['a1' => ['b1', ['b2', 'z9']]], 'bob' => ['b1' => [], 'b2' => []]]);
    }

    public function testRefusesAWishForAnOfferOfTheSameParticipant(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The offer "a1" wishes for "a2", an offer of the same participant.');

        Lists::of(['alice' => ['a1' => ['b1', 'a2'], 'a2' => []], 'bob' => ['b1' => []]]);
    }

    public function testAcceptsAListAtItsLimits(): void
    {
        $list = Lists::of(
            ['alice' => ['a1' => [['b1', 'b2']], 'a2' => ['b1']], 'bob' => ['b1' => ['a1'], 'b2' => []]],
            new Limits(participants: 2, offers: 4, offersPerParticipant: 2, wishesPerOffer: 1, groupSize: 2, wishedOffers: 4),
        );

        self::assertCount(4, $list->offers());
    }

    public function testRefusesTooManyParticipants(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The trade list holds more than 1 participants.');

        Lists::of(['alice' => ['a1' => []], 'bob' => ['b1' => []]], new Limits(participants: 1));
    }

    public function testRefusesTooManyOffersOfAParticipant(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The participant "alice" makes more than 1 offers.');

        Lists::of(['alice' => ['a1' => [], 'a2' => []]], new Limits(offersPerParticipant: 1));
    }

    public function testRefusesTooManyOffers(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The trade list holds more than 2 offers.');

        Lists::of(['alice' => ['a1' => [], 'a2' => []], 'bob' => ['b1' => []]], new Limits(offers: 2));
    }

    public function testRefusesTooManyWishes(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The offer "a1" lists more than 1 wishes.');

        Lists::of(['alice' => ['a1' => ['b1', 'b2']], 'bob' => ['b1' => [], 'b2' => []]], new Limits(wishesPerOffer: 1));
    }

    public function testRefusesTooLargeAGroup(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('A group of wishes of the offer "a1" names more than 2 offers.');

        Lists::of(['alice' => ['a1' => [['b1', 'b2', 'b3']]], 'bob' => ['b1' => [], 'b2' => [], 'b3' => []]], new Limits(groupSize: 2));
    }

    public function testRefusesTooManyWishedOffers(): void
    {
        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('The trade list wishes for more than 2 offers.');

        Lists::of(['alice' => ['a1' => ['b1'], 'a2' => [['b1', 'b2']]], 'bob' => ['b1' => [], 'b2' => []]], new Limits(wishedOffers: 2));
    }

    public function testEveryRefusalIsAnExceptionOfTheLibrary(): void
    {
        self::assertInstanceOf(MathTradeException::class, InvalidTradeList::because('x'));
        self::assertInstanceOf(MathTradeException::class, LimitExceeded::because('x'));
    }
}
