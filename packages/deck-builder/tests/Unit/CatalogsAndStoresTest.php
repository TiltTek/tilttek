<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DeckBuilder\Card;
use Tilttek\DeckBuilder\Catalog\InMemoryCardCatalog;
use Tilttek\DeckBuilder\Catalog\InMemoryFormatCatalog;
use Tilttek\DeckBuilder\Catalog\OwnedCardList;
use Tilttek\DeckBuilder\Deck;
use Tilttek\DeckBuilder\DeckVersion;
use Tilttek\DeckBuilder\Exception\InvalidDeck;
use Tilttek\DeckBuilder\Format;
use Tilttek\DeckBuilder\Store\InMemoryDeckStore;

#[CoversClass(InMemoryCardCatalog::class)]
#[CoversClass(InMemoryFormatCatalog::class)]
#[CoversClass(OwnedCardList::class)]
#[CoversClass(InMemoryDeckStore::class)]
final class CatalogsAndStoresTest extends TestCase
{
    public function testTheCatalogsFindWhatTheyHold(): void
    {
        $fox = new Card('fox', 'Fox');
        $cards = new InMemoryCardCatalog($fox, new Card('owl', 'Owl'));
        $format = new Format('f', 'F');
        $formats = new InMemoryFormatCatalog($format);

        self::assertSame($fox, $cards->find('fox'));
        self::assertNull($cards->find('bat'));
        self::assertSame($format, $formats->find('f'));
        self::assertNull($formats->find('g'));
    }

    public function testACollectionCountsTheCopiesItOwns(): void
    {
        $owned = new OwnedCardList(['fox' => 3, '7' => 0]);

        self::assertSame(3, $owned->copiesOf('fox'));
        self::assertSame(0, $owned->copiesOf('7'));
        self::assertSame(0, $owned->copiesOf('owl'));
    }

    public function testACollectionRefusesANegativeCount(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('A collection owns 0 or more copies of the card "fox"; -1 is not.');

        new OwnedCardList(['fox' => -1]);
    }

    public function testACollectionChecksItsCards(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a card is');

        new OwnedCardList(['a b' => 1]);
    }

    public function testTheStoreKeepsEachVersionOnceInOrder(): void
    {
        $store = new InMemoryDeckStore();
        $at = new DateTimeImmutable();
        $two = new DeckVersion(new Deck('d', 'D', 'f'), 2, $at);
        $one = new DeckVersion(new Deck('d', 'D', 'f'), 1, $at);

        self::assertNull($store->latest('d'));
        self::assertSame([], $store->versions('d'));
        self::assertTrue($store->add($two));
        self::assertTrue($store->add($one));
        self::assertFalse($store->add(new DeckVersion(new Deck('d', 'Other', 'f'), 1, $at)));
        self::assertSame([$one, $two], $store->versions('d'));
        self::assertSame($two, $store->latest('d'));
        self::assertSame($one, $store->version('d', 1));
        self::assertNull($store->version('d', 3));
        self::assertNull($store->version('e', 1));
        self::assertTrue($store->add(new DeckVersion(new Deck('e', 'E', 'f'), 1, $at)));
        self::assertSame([$one, $two], $store->versions('d'));
        self::assertCount(1, $store->versions('e'));
    }
}
