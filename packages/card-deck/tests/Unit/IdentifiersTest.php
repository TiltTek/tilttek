<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\CardDeck\Card;
use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Deck;
use Tilttek\CardDeck\Exception\InvalidDefinition;
use Tilttek\CardDeck\Face;
use Tilttek\CardDeck\GameId;
use Tilttek\CardDeck\Name;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\StandardDeck;
use Tilttek\CardDeck\ZoneId;
use Tilttek\CardDeck\ZoneKind;

#[CoversClass(Name::class)]
#[CoversClass(CardId::class)]
#[CoversClass(PlayerId::class)]
#[CoversClass(GameId::class)]
#[CoversClass(Face::class)]
#[CoversClass(Card::class)]
#[CoversClass(Deck::class)]
#[CoversClass(StandardDeck::class)]
#[CoversClass(ZoneId::class)]
#[CoversClass(InvalidDefinition::class)]
final class IdentifiersTest extends TestCase
{
    public function testNamesFollowOnePattern(): void
    {
        self::assertSame('a', new CardId('a')->value);
        self::assertSame('9._-Z', new PlayerId('9._-Z')->value);
        self::assertSame(str_repeat('g', 64), new GameId(str_repeat('g', 64))->value);
        self::assertSame('AS', new Face('AS')->key);
        self::assertTrue(new CardId('x')->equals(new CardId('x')));
        self::assertFalse(new CardId('x')->equals(new CardId('y')));
        self::assertTrue(new PlayerId('x')->equals(new PlayerId('x')));
        self::assertFalse(new PlayerId('x')->equals(new PlayerId('y')));
        self::assertTrue(new GameId('x')->equals(new GameId('x')));
        self::assertFalse(new GameId('x')->equals(new GameId('y')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNames(): iterable
    {
        yield 'empty' => [''];
        yield 'leading dot' => ['.a'];
        yield 'leading hyphen' => ['-a'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'colon' => ['a:b'];
        yield 'space' => ['a b'];
        yield 'trailing line break' => ["a\n"];
        yield 'leading text' => ["x\na"];
        yield 'accent' => ['é'];
    }

    #[DataProvider('badNames')]
    public function testANameOutOfThePatternIsRefused(string $name): void
    {
        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('A card identifier is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter or a digit.');

        new CardId($name);
    }

    public function testEachNameSaysWhatItIs(): void
    {
        foreach ([
            'A player identifier' => static fn (): PlayerId => new PlayerId(''),
            'A game identifier' => static fn (): GameId => new GameId(''),
            'A face key' => static fn (): Face => new Face(''),
            'A pile name' => static fn (): ZoneId => ZoneId::pile(''),
        ] as $what => $make) {
            try {
                $make();
                self::fail($what);
            } catch (InvalidDefinition $invalid) {
                self::assertStringStartsWith($what.' is 1 to 64', $invalid->getMessage());
            }
        }
    }

    public function testACardHasOneToSixteenFaces(): void
    {
        $faces = array_map(static fn (int $index): Face => new Face('f'.$index), range(1, 16));
        $card = new Card(new CardId('c'), $faces, new Face('back'));

        self::assertSame($faces, $card->faces);
        self::assertSame('back', $card->back->key);

        $one = Card::of('c', 'AS');
        self::assertSame('c', $one->id->value);
        self::assertSame('AS', $one->faces[0]->key);
        self::assertSame('back', $one->back->key);
        self::assertSame('blue', Card::of('c', 'AS', 'blue')->back->key);

        foreach ([[], [...$faces, new Face('f17')]] as $wrong) {
            try {
                new Card(new CardId('c'), $wrong, new Face('back'));
                self::fail('A card out of bounds was accepted.');
            } catch (InvalidDefinition $invalid) {
                self::assertSame('A card has 1 to 16 faces.', $invalid->getMessage());
            }
        }
    }

    public function testADeckKeepsItsOrderAndRefusesTwins(): void
    {
        $deck = new Deck(Card::of('b', 'X'), Card::of('a', 'Y'));

        self::assertSame(['b', 'a'], array_map(static fn (Card $card): string => $card->id->value, $deck->cards()));
        self::assertTrue($deck->has(new CardId('a')));
        self::assertFalse($deck->has(new CardId('z')));
        self::assertSame('Y', $deck->card(new CardId('a'))->faces[0]->key);

        try {
            $deck->card(new CardId('z'));
            self::fail('An unknown card was found.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertSame('The deck holds no card "z".', $invalidDefinition->getMessage());
        }

        $this->expectExceptionObject(InvalidDefinition::because('The card "a" is twice in the deck.'));
        new Deck(Card::of('a', 'X'), Card::of('a', 'Y'));
    }

    public function testADeckHoldsOneToMaxCards(): void
    {
        $cards = array_map(static fn (int $index): Card => Card::of('c'.$index, 'X'), range(1, Deck::MAX_CARDS));
        self::assertCount(1024, new Deck(...$cards)->cards());

        foreach ([[], [...$cards, Card::of('extra', 'X')]] as $wrong) {
            try {
                new Deck(...$wrong);
                self::fail('A deck out of bounds was accepted.');
            } catch (InvalidDefinition $invalid) {
                self::assertSame('A deck holds 1 to 1024 cards.', $invalid->getMessage());
            }
        }
    }

    public function testADeckGoesToAnArrayAndBack(): void
    {
        $deck = new Deck(new Card(new CardId('m'), [new Face('day'), new Face('night')], new Face('moon')), Card::of('j', 'JK'));
        $array = $deck->toArray();

        self::assertSame([['id' => 'm', 'faces' => ['day', 'night'], 'back' => 'moon'], ['id' => 'j', 'faces' => ['JK'], 'back' => 'back']], $array);
        self::assertEquals($deck, Deck::fromArray($array));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badDecks(): iterable
    {
        $card = ['id' => 'a', 'faces' => ['X'], 'back' => 'b'];

        yield 'not an array' => ['deck', 'A deck is a list of cards.'];
        yield 'not a list' => [['x' => $card], 'A deck is a list of cards.'];
        yield 'card not an array' => [['a'], 'A card is an identifier, a list of faces and a back.'];
        yield 'no identifier' => [[['faces' => ['X'], 'back' => 'b']], 'A card is an identifier, a list of faces and a back.'];
        yield 'identifier not a string' => [[['id' => 1, 'faces' => ['X'], 'back' => 'b']], 'A card is an identifier, a list of faces and a back.'];
        yield 'no back' => [[['id' => 'a', 'faces' => ['X']]], 'A card is an identifier, a list of faces and a back.'];
        yield 'no faces' => [[['id' => 'a', 'back' => 'b']], 'A card is an identifier, a list of faces and a back.'];
        yield 'faces not a list' => [[['id' => 'a', 'faces' => ['f' => 'X'], 'back' => 'b']], 'A card is an identifier, a list of faces and a back.'];
        yield 'face not a string' => [[['id' => 'a', 'faces' => [1], 'back' => 'b']], 'A face is a key.'];
        yield 'empty' => [[], 'A deck holds 1 to 1024 cards.'];
    }

    #[DataProvider('badDecks')]
    public function testADeckThatDoesNotDecodeIsRefused(mixed $data, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        Deck::fromArray($data);
    }

    public function testTheStandardDeckHas52CardsAndItsJokers(): void
    {
        $deck = StandardDeck::create();
        $faces = array_map(static fn (Card $card): string => $card->faces[0]->key, $deck->cards());

        self::assertCount(52, $faces);
        self::assertSame(['AS', '2S', '3S', '4S', '5S', '6S', '7S', '8S', '9S', '10S', 'JS', 'QS', 'KS', 'AH'], \array_slice($faces, 0, 14));
        self::assertSame('KC', $faces[51]);
        self::assertSame('card-01', $deck->cards()[0]->id->value);
        self::assertSame('card-52', $deck->cards()[51]->id->value);
        self::assertSame('back', $deck->cards()[0]->back->key);

        $jokers = StandardDeck::create(2, 'red')->cards();
        self::assertCount(54, $jokers);
        self::assertSame(['JK', 'JK'], [$jokers[52]->faces[0]->key, $jokers[53]->faces[0]->key]);
        self::assertSame(['card-53', 'card-54'], [$jokers[52]->id->value, $jokers[53]->id->value]);
        self::assertSame('red', $jokers[53]->back->key);
        self::assertCount(52, StandardDeck::create(-3)->cards());
    }

    public function testZonesAreWrittenAndReadBack(): void
    {
        $zones = [ZoneId::drawPile(), ZoneId::discard(), ZoneId::table(), ZoneId::hand(new PlayerId('p1')), ZoneId::pile('market')];

        self::assertSame(['draw', 'discard', 'table', 'hand:p1', 'pile:market'], array_map(static fn (ZoneId $zone): string => $zone->toString(), $zones));

        foreach ($zones as $zone) {
            self::assertEquals($zone, ZoneId::fromString($zone->toString()));
            self::assertTrue($zone->equals(ZoneId::fromString($zone->toString())));
        }

        self::assertSame(ZoneKind::Hand, $zones[3]->kind);
        self::assertSame('p1', $zones[3]->name);
        self::assertNull($zones[0]->name);
        self::assertFalse(ZoneId::pile('p1')->equals($zones[3]));
        self::assertTrue($zones[3]->isHandOf(new PlayerId('p1')));
        self::assertFalse($zones[3]->isHandOf(new PlayerId('p2')));
        self::assertFalse($zones[3]->isHandOf(null));
        self::assertFalse(ZoneId::pile('p1')->isHandOf(new PlayerId('p1')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badZones(): iterable
    {
        yield 'unknown kind' => ['deck', 'A zone is draw, discard, table, hand:<player> or pile:<name>.'];
        yield 'empty' => ['', 'A zone is draw, discard, table, hand:<player> or pile:<name>.'];
        yield 'named draw pile' => ['draw:x', 'The zone "draw" takes no name.'];
        yield 'named table' => ['table:', 'The zone "table" takes no name.'];
        yield 'hand without player' => ['hand', 'A player identifier is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter or a digit.'];
        yield 'pile without name' => ['pile:', 'A pile name is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter or a digit.'];
        yield 'hand of two colons' => ['hand:a:b', 'A player identifier is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter or a digit.'];
    }

    #[DataProvider('badZones')]
    public function testAZoneThatDoesNotDecodeIsRefused(string $zone, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));

        ZoneId::fromString($zone);
    }
}
