<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DeckBuilder\ArrayInput;
use Tilttek\DeckBuilder\Catalog\OwnedCardList;
use Tilttek\DeckBuilder\Contract\OwnedCards;
use Tilttek\DeckBuilder\Deck;
use Tilttek\DeckBuilder\DeckReference;
use Tilttek\DeckBuilder\Exception\InvalidDeck;

#[CoversClass(Deck::class)]
#[CoversClass(DeckReference::class)]
#[CoversClass(ArrayInput::class)]
final class DeckTest extends TestCase
{
    public function testADeckKeepsItsCardsInTheOrderTheyWereAdded(): void
    {
        $deck = new Deck('d-1', 'Foxes', 'open')
            ->withCard('fox', 2)
            ->withCard('den')
            ->withCard('fox');

        self::assertSame(['fox' => 3, 'den' => 1], $deck->cards);
        self::assertSame(4, $deck->size());
        self::assertSame(3, $deck->quantityOf('fox'));
        self::assertSame(0, $deck->quantityOf('owl'));
        self::assertNull($deck->copiedFrom);
        self::assertSame('d-1', $deck->id);
        self::assertSame('Foxes', $deck->name);
        self::assertSame('open', $deck->format);
    }

    public function testCardsAreRemovedAndSet(): void
    {
        $deck = new Deck('d', 'D', 'f', ['a' => 3, 'b' => 1, 'c' => 2]);

        self::assertSame(['a' => 2, 'b' => 1, 'c' => 2], $deck->withoutCard('a')->cards);
        self::assertSame(['a' => 3, 'c' => 2], $deck->withoutCard('b')->cards);
        self::assertSame(['b' => 1, 'c' => 2], $deck->withoutCard('a', 5)->cards);
        self::assertSame(['a' => 3, 'b' => 1, 'c' => 2], $deck->withoutCard('z')->cards);
        self::assertSame(['a' => 3, 'b' => 4, 'c' => 2], $deck->withQuantity('b', 4)->cards);
        self::assertSame(['a' => 3, 'c' => 2], $deck->withQuantity('b', 0)->cards);
        self::assertSame(['a' => 3, 'b' => 1, 'c' => 2, 'd' => 1], $deck->withQuantity('d', 1)->cards);
        self::assertSame('Other', $deck->renamed('Other')->name);
        self::assertSame(['a' => 3, 'b' => 1, 'c' => 2], $deck->renamed('Other')->cards);
        self::assertSame(['a' => 3, 'b' => 1, 'c' => 2], $deck->cards);
    }

    public function testAChangeKeepsTheOriginal(): void
    {
        $original = new DeckReference('src', 2);
        $deck = new Deck('d', 'D', 'f', ['a' => 1], $original);

        self::assertSame($original, $deck->withCard('b')->copiedFrom);
        self::assertSame($original, $deck->withoutCard('a')->copiedFrom);
        self::assertSame($original, $deck->renamed('E')->copiedFrom);
        self::assertSame($original, $deck->limitedTo(new OwnedCardList([]))->copiedFrom);
    }

    public function testACopyTakesANewIdentifierAndLinksToItsOriginal(): void
    {
        $deck = new Deck('d', 'D', 'f', ['a' => 2]);
        $copy = $deck->copiedAs('e', 'E', new DeckReference('d', 3));

        self::assertSame('e', $copy->id);
        self::assertSame('E', $copy->name);
        self::assertSame('f', $copy->format);
        self::assertSame(['a' => 2], $copy->cards);
        self::assertSame(['deck' => 'd', 'version' => 3], $copy->copiedFrom?->toArray());
    }

    public function testADeckIsLimitedToTheCardsACollectionOwns(): void
    {
        $deck = new Deck('d', 'D', 'f', ['a' => 3, 'b' => 2, 'c' => 1, 'd' => 1]);
        $limited = $deck->limitedTo(new OwnedCardList(['a' => 1, 'b' => 5, 'd' => 0]));

        self::assertSame(['a' => 1, 'b' => 2], $limited->cards);
        self::assertSame(['b' => 2], $deck->limitedTo(new class implements OwnedCards {
            public function copiesOf(string $card): int
            {
                return 'b' === $card ? 9 : -1;
            }
        })->cards);
        self::assertSame(['7' => 1], new Deck('d', 'D', 'f', ['7' => 2, '8' => 1])->limitedTo(new OwnedCardList(['7' => 1]))->cards);
        self::assertSame('d', $limited->id);
        self::assertSame('D', $limited->name);
        self::assertSame('f', $limited->format);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedChanges(): iterable
    {
        $deck = new Deck('d', 'D', 'f', ['a' => 998]);

        yield 'no copies added' => [static fn (): Deck => $deck->withCard('b', 0), 'A deck holds 1 to 999 copies of the card "b"; 0 is not.'];
        yield 'beyond 999 copies' => [static fn (): Deck => $deck->withCard('a', 2), 'A deck holds 1 to 999 copies of the card "a"; 1000 is not.'];
        yield 'too many added at once' => [static fn (): Deck => $deck->withCard('b', 1000), 'A deck holds 1 to 999 copies of the card "b"; 1000 is not.'];
        yield 'no copies removed' => [static fn (): Deck => $deck->withoutCard('a', 0), 'A deck holds 1 to 999 copies of the card "a"; 0 is not.'];
        yield 'negative quantity' => [static fn (): Deck => $deck->withQuantity('a', -1), 'A deck holds 1 to 999 copies of the card "a"; -1 is not.'];
        yield 'card refused' => [static fn (): Deck => $deck->withCard('a b'), 'The identifier of a card is'];
        yield 'name refused' => [static fn (): Deck => $deck->renamed(''), 'The name of a deck is'];
        yield 'identifier refused' => [static fn (): Deck => new Deck('', 'D', 'f'), 'The identifier of a deck is'];
        yield 'format refused' => [static fn (): Deck => new Deck('d', 'D', 'F'), 'The code of a format is'];
        yield 'too many cards' => [static fn (): Deck => new Deck('d', 'D', 'f', array_fill_keys(array_map(static fn (int $card): string => 'c'.$card, range(0, 1000)), 1)), 'A deck holds at most 1000 distinct cards.'];
        yield 'version of zero' => [static fn (): DeckReference => new DeckReference('d', 0), 'A version of a deck is 1 or more; 0 is not.'];
        yield 'reference to a refused deck' => [static fn (): DeckReference => new DeckReference('', 1), 'The identifier of a deck is'];
    }

    /**
     * @param callable(): mixed $change
     */
    #[DataProvider('refusedChanges')]
    public function testRefusedChangesSayWhy(callable $change, string $message): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains($message);

        $change();
    }

    public function testALongCardIdentifierIsCutInTheMessageOfARefusedQuantity(): void
    {
        $card = str_repeat('a', 60).'bcdefghij';

        try {
            new Deck('d', 'D', 'f')->withCard($card, 0);
            self::fail('A refused quantity was accepted.');
        } catch (InvalidDeck $invalidDeck) {
            self::assertSame('A deck holds 1 to 999 copies of the card "'.substr($card, 0, 64).'"; 0 is not.', $invalidDeck->getMessage());
        }
    }

    public function testOneThousandCardsAndNineHundredNinetyNineCopiesAreAccepted(): void
    {
        $deck = new Deck('d', 'D', 'f', array_fill_keys(array_map(static fn (int $card): string => 'c'.$card, range(1, 1000)), 999));

        self::assertSame(999_000, $deck->size());
        self::assertSame(1, new DeckReference('d', 1)->version);
    }

    public function testADeckWritesAndReadsBack(): void
    {
        $deck = new Deck('d/1', 'Été', 'open', ['0' => 2, '1' => 1, 'x' => 3], new DeckReference('src', 4));
        $json = $deck->toJson();

        self::assertSame('{"id":"d/1","name":"Été","format":"open","cards":{"0":2,"1":1,"x":3},"copied_from":{"deck":"src","version":4}}', $json);
        self::assertTrue(Deck::fromJson($json)->equals($deck));
        self::assertTrue(Deck::fromArray($deck->toArray())->equals($deck));
        self::assertSame('{"id":"d","name":"D","format":"f","cards":{},"copied_from":null}', new Deck('d', 'D', 'f')->toJson());
        self::assertTrue(Deck::fromJson('{"id":"d","name":"D","format":"f","cards":[2,1]}')->equals(new Deck('d', 'D', 'f', ['0' => 2, '1' => 1])));
        self::assertTrue(Deck::fromJson('{"id":"d","name":"D","format":"f"}')->equals(new Deck('d', 'D', 'f')));
    }

    public function testTheOrderOfTheCardsMattersToEquality(): void
    {
        self::assertFalse(new Deck('d', 'D', 'f', ['a' => 1, 'b' => 1])->equals(new Deck('d', 'D', 'f', ['b' => 1, 'a' => 1])));
        self::assertFalse(new Deck('d', 'D', 'f')->equals(new Deck('d', 'D', 'f', [], new DeckReference('e', 1))));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedJson(): iterable
    {
        yield 'not JSON' => ['nope', 'A deck in JSON does not read: Syntax error'];
        yield 'nested as deep as allowed' => [str_repeat('[', 15).str_repeat(']', 15), 'A deck is an object of keys.'];
        yield 'nested too deep' => [str_repeat('[', 16).str_repeat(']', 16), 'A deck in JSON does not read: Maximum stack depth exceeded'];
        yield 'an unknown key' => ['{"id":"d","name":"D","format":"f","owner":"x"}', 'A deck has no key "owner".'];
        yield 'no format' => ['{"id":"d","name":"D"}', 'A deck needs its "format".'];
        yield 'cards not integers' => ['{"id":"d","name":"D","format":"f","cards":{"a":true}}', 'The "cards" of a deck is an object of integers.'];
        yield 'cards a string' => ['{"id":"d","name":"D","format":"f","cards":"a"}', 'The "cards" of a deck is an object of integers.'];
        yield 'a reference not an object' => ['{"id":"d","name":"D","format":"f","copied_from":"e"}', 'A deck reference is an object of keys.'];
        yield 'a reference without version' => ['{"id":"d","name":"D","format":"f","copied_from":{"deck":"e"}}', 'A deck reference needs its "version".'];
        yield 'a reference of a string version' => ['{"id":"d","name":"D","format":"f","copied_from":{"deck":"e","version":"1"}}', 'The "version" of a deck reference is an integer.'];
    }

    #[DataProvider('refusedJson')]
    public function testJsonThatDoesNotReadIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains($message);

        Deck::fromJson($json);
    }

    public function testMoreThanOneThousandCardsDoNotRead(): void
    {
        $this->expectException(InvalidDeck::class);
        $this->expectExceptionMessageIsOrContains('A deck has at most 1000 "cards".');

        Deck::fromArray(['id' => 'd', 'name' => 'D', 'format' => 'f', 'cards' => array_fill(0, 1001, 1)]);
    }
}
