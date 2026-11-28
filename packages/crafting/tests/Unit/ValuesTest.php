<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Crafting\Craft;
use Tilttek\Crafting\Exception\InvalidRecipe;
use Tilttek\Crafting\Holding;
use Tilttek\Crafting\Ingredient;
use Tilttek\Crafting\ItemQuantity;
use Tilttek\Crafting\Policy\ProtectedItems;
use Tilttek\Crafting\Requirement;
use Tilttek\Crafting\Selection;
use Tilttek\Crafting\Text;

#[CoversClass(Text::class)]
#[CoversClass(ItemQuantity::class)]
#[CoversClass(Holding::class)]
#[CoversClass(Ingredient::class)]
#[CoversClass(Selection::class)]
#[CoversClass(Requirement::class)]
#[CoversClass(Craft::class)]
#[CoversClass(ProtectedItems::class)]
#[CoversClass(InvalidRecipe::class)]
final class ValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validCodes(): iterable
    {
        yield 'one character' => ['a'];
        yield 'digits after a letter' => ['d20'];
        yield 'dots, dashes and underscores' => ['ore.iron-bar_2'];
        yield 'ninety-six characters' => [str_repeat('a', 96)];
    }

    #[DataProvider('validCodes')]
    public function testACodeIsKept(string $code): void
    {
        self::assertSame($code, Text::code($code, 'an item'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Iron'];
        yield 'space' => ['iron bar'];
        yield 'leading dot' => ['.iron'];
        yield 'trailing dot' => ['iron.'];
        yield 'double dot' => ['iron..bar'];
        yield 'ninety-seven characters' => [str_repeat('a', 97)];
        yield 'new line after' => ["iron\n"];
        yield 'prefix before a valid code' => ['!iron'];
        yield 'digits only' => ['123'];
        yield 'a digit first' => ['2handed'];
        yield 'a dash first' => ['-iron'];
    }

    #[DataProvider('invalidCodes')]
    public function testACodeOutOfBoundsIsRefused(string $code): void
    {
        $this->expectException(InvalidRecipe::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The code of an item must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them, starting with a letter; "%s" is not.', $code));

        Text::code($code, 'an item');
    }

    public function testAKeyIsVisibleAscii(): void
    {
        self::assertSame('craft:42#1', Text::key('craft:42#1'));
        self::assertSame(str_repeat('k', 191), Text::key(str_repeat('k', 191)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['craft 1'];
        yield 'too long' => [str_repeat('k', 192)];
        yield 'not ascii' => ['créer'];
        yield 'new line after' => ["craft\n"];
        yield 'space before' => [' craft'];
    }

    #[DataProvider('invalidKeys')]
    public function testAKeyOutOfBoundsIsRefused(string $key): void
    {
        $this->expectException(InvalidRecipe::class);
        $this->expectExceptionMessageIsOrContains('The key of a craft is 1 to 191 visible ASCII characters, without spaces.');

        Text::key($key);
    }

    public function testAQuantityIsBounded(): void
    {
        self::assertSame(1, Text::quantity(1, 'x', 5));
        self::assertSame(5, Text::quantity(5, 'x', 5));

        try {
            Text::quantity(0, 'the thing', 5);
            self::fail('Zero was kept.');
        } catch (InvalidRecipe $invalidRecipe) {
            self::assertSame('The quantity of the thing is 1 to 5; it is 0.', $invalidRecipe->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The quantity of the thing is 1 to 5; it is 6.');
        Text::quantity(6, 'the thing', 5);
    }

    public function testAnItemQuantity(): void
    {
        $quantity = new ItemQuantity('iron-bar', 3);

        self::assertSame(['item' => 'iron-bar', 'quantity' => 3], $quantity->toArray());
        self::assertSame(1, new ItemQuantity('iron-bar')->quantity);
        self::assertSame(ItemQuantity::MAX_MOVED, new ItemQuantity('iron-bar', 99_900)->quantity);
        self::assertSame(99_900, ItemQuantity::MAX_MOVED);
    }

    public function testAnItemQuantityAboveTheMostMovedIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The quantity of "iron-bar" is 1 to 99900; it is 99901.');

        new ItemQuantity('iron-bar', 99_901);
    }

    public function testAnItemQuantityNamesAnItem(): void
    {
        $this->expectExceptionMessageIsOrContains('The code of an item must be');

        new ItemQuantity('Iron');
    }

    public function testAListOfQuantitiesIsInTheOrderOfTheCodes(): void
    {
        $list = ItemQuantity::listOf(['wood' => 2, 'd10' => 1, 'iron' => 3]);

        self::assertSame([['item' => 'd10', 'quantity' => 1], ['item' => 'iron', 'quantity' => 3], ['item' => 'wood', 'quantity' => 2]], array_map(static fn (ItemQuantity $item): array => $item->toArray(), $list));
    }

    public function testAHoldingSortsItsTagsOnce(): void
    {
        $holding = new Holding('iron-ore', 4, ['metal', 'heavy', 'metal']);

        self::assertSame(['heavy', 'metal'], $holding->tags);
        self::assertTrue($holding->hasTag('metal'));
        self::assertFalse($holding->hasTag('herb'));
        self::assertSame([], new Holding('wood', 1)->tags);
        self::assertSame(Holding::MAX_QUANTITY, new Holding('wood', 1_000_000_000)->quantity);
    }

    public function testAHoldingCarriesSixteenTagsAtMost(): void
    {
        $tags = array_map(static fn (int $i): string => 't'.$i, range(1, 16));
        self::assertCount(16, new Holding('wood', 1, $tags)->tags);

        $this->expectExceptionMessageIsOrContains('An item carries at most 16 tags; "wood" has 17.');
        new Holding('wood', 1, [...$tags, 't17']);
    }

    public function testAHoldingIsBounded(): void
    {
        $this->expectExceptionMessageIsOrContains('The quantity of the holding of "wood" is 1 to 1000000000; it is 0.');

        new Holding('wood', 0);
    }

    public function testAHoldingChecksItsItem(): void
    {
        $this->expectExceptionMessageIsOrContains('The code of an item must be');

        new Holding('Wood', 1);
    }

    public function testAHoldingChecksItsTags(): void
    {
        $this->expectExceptionMessageIsOrContains('The code of a tag must be');

        new Holding('wood', 1, ['Tag']);
    }

    public function testAnIngredientByItem(): void
    {
        $ingredient = Ingredient::item('iron-bar', 2);

        self::assertSame('iron-bar', $ingredient->item);
        self::assertNull($ingredient->tag);
        self::assertTrue($ingredient->consumed);
        self::assertSame('iron-bar', $ingredient->label());
        self::assertSame(6, $ingredient->needed(3));
        self::assertTrue($ingredient->accepts(new Holding('iron-bar', 1)));
        self::assertFalse($ingredient->accepts(new Holding('iron-ore', 1, ['iron-bar'])));
        self::assertSame(['item' => 'iron-bar', 'quantity' => 2, 'consumed' => true], $ingredient->toArray());
        self::assertSame(1, Ingredient::item('iron-bar')->quantity);
    }

    public function testAToolIsNeededOnceForEveryRepetition(): void
    {
        $tool = Ingredient::item('hammer', 1, false);

        self::assertFalse($tool->consumed);
        self::assertSame(1, $tool->needed(5));
        self::assertSame(['item' => 'hammer', 'quantity' => 1, 'consumed' => false], $tool->toArray());
    }

    public function testAnIngredientByTag(): void
    {
        $ingredient = Ingredient::tagged('rare-metal', 2, false);

        self::assertNull($ingredient->item);
        self::assertSame('rare-metal', $ingredient->tag);
        self::assertSame('#rare-metal', $ingredient->label());
        self::assertTrue($ingredient->accepts(new Holding('mithril', 1, ['rare-metal'])));
        self::assertFalse($ingredient->accepts(new Holding('rare-metal', 1)));
        self::assertSame(['tag' => 'rare-metal', 'quantity' => 2, 'consumed' => false], $ingredient->toArray());
        self::assertSame(1, Ingredient::tagged('rare-metal')->quantity);
        self::assertTrue(Ingredient::tagged('rare-metal')->consumed);
    }

    public function testAnIngredientQuantityIsBounded(): void
    {
        self::assertSame(999, Ingredient::item('wood', 999)->quantity);

        $this->expectExceptionMessageIsOrContains('The quantity of the ingredient #herb is 1 to 999; it is 1000.');
        Ingredient::tagged('herb', 1000);
    }

    public function testAnIngredientChecksItsCodes(): void
    {
        try {
            Ingredient::item('Wood');
            self::fail('An item code was not checked.');
        } catch (InvalidRecipe $invalidRecipe) {
            self::assertStringStartsWith('The code of an item', $invalidRecipe->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The code of a tag must be');
        Ingredient::tagged('Herb');
    }

    public function testASelectionIsSortedAndChecked(): void
    {
        $selection = new Selection([3 => ['tin' => 1, 'copper' => 2], 0 => ['oak' => 1]]);

        self::assertSame([0 => ['oak' => 1], 3 => ['copper' => 2, 'tin' => 1]], $selection->choices);
        self::assertSame(['copper' => 2, 'tin' => 1], $selection->for(3));
        self::assertNull($selection->for(1));
        self::assertSame(['d1' => 4], new Selection([15 => ['d1' => 4]])->for(15));
    }

    /**
     * @return iterable<string, array{array<int, array<string, int>>, string}>
     */
    public static function invalidSelections(): iterable
    {
        yield 'negative position' => [[-1 => ['oak' => 1]], 'A selection names the ingredients by their position, 0 to 15; -1 is not one.'];
        yield 'position sixteen' => [[16 => ['oak' => 1]], 'A selection names the ingredients by their position, 0 to 15; 16 is not one.'];
        yield 'no item' => [[0 => []], 'A selection chooses 1 to 16 items for an ingredient; 0 were chosen for the ingredient 0.'];
        yield 'seventeen items' => [[2 => array_fill_keys(array_map(static fn (int $i): string => 'i'.$i, range(1, 17)), 1)], 'A selection chooses 1 to 16 items for an ingredient; 17 were chosen for the ingredient 2.'];
        yield 'bad item' => [[0 => ['Oak' => 1]], 'The code of an item must be'];
        yield 'zero' => [[0 => ['oak' => 0]], 'The quantity of "oak" in the selection is 1 to 99900; it is 0.'];
    }

    /**
     * @param array<int, array<string, int>> $choices
     */
    #[DataProvider('invalidSelections')]
    public function testASelectionOutOfBoundsIsRefused(array $choices, string $message): void
    {
        $this->expectException(InvalidRecipe::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Selection($choices);
    }

    public function testASelectionOfSixteenItems(): void
    {
        $items = array_fill_keys(array_map(static fn (int $i): string => 'i'.$i, range(1, 16)), 1);

        self::assertCount(16, (array) new Selection([0 => $items])->for(0));
    }

    public function testARequirement(): void
    {
        $met = new Requirement(Ingredient::tagged('metal', 3), 3, ['iron' => 2, 'tin' => 1]);
        $short = new Requirement(Ingredient::item('wood', 4), 4, ['wood' => 1]);
        $over = new Requirement(Ingredient::item('wood', 1), 1, ['wood' => 2]);

        self::assertSame(3, $met->held());
        self::assertSame(0, $met->missing());
        self::assertTrue($met->isMet());
        self::assertSame(3, $short->missing());
        self::assertFalse($short->isMet());
        self::assertSame(0, $over->missing());
        self::assertSame(0, new Requirement(Ingredient::item('wood'), 1, [])->held());
    }

    public function testACraft(): void
    {
        $holder = ResourceReference::of('member', '7');
        $at = new DateTimeImmutable('2026-11-27 10:00:00+00:00');
        $craft = new Craft('c#1', $holder, 'egg.hatch', 2, [new ItemQuantity('egg')], [new ItemQuantity('nest')], [new ItemQuantity('shell')], [new ItemQuantity('dragon')], $at, 'egg');

        self::assertTrue($craft->isFor(ResourceReference::of('member', '7'), 'egg.hatch', 2));
        self::assertFalse($craft->isFor(ResourceReference::of('member', '8'), 'egg.hatch', 2));
        self::assertFalse($craft->isFor($holder, 'egg.boil', 2));
        self::assertFalse($craft->isFor($holder, 'egg.hatch', 1));
        self::assertSame([
            'key' => 'c#1',
            'holder' => 'member:7',
            'recipe' => 'egg.hatch',
            'times' => 2,
            'consumed' => [['item' => 'egg', 'quantity' => 1]],
            'used' => [['item' => 'nest', 'quantity' => 1]],
            'returned' => [['item' => 'shell', 'quantity' => 1]],
            'produced' => [['item' => 'dragon', 'quantity' => 1]],
            'at' => '2026-11-27T10:00:00+00:00',
            'evolved' => 'egg',
        ], $craft->toArray());
        self::assertNull(new Craft('c#2', $holder, 'egg.hatch', 1, [], [], [], [], $at)->evolved);
    }

    /**
     * @return iterable<string, array{string, string, int, ?string, string}>
     */
    public static function invalidCrafts(): iterable
    {
        yield 'key' => ['bad key', 'r', 1, null, 'The key of a craft'];
        yield 'recipe' => ['k', 'R', 1, null, 'The code of a recipe'];
        yield 'no repetition' => ['k', 'r', 0, null, 'The quantity of repetitions of a craft is 1 to 100; it is 0.'];
        yield 'too many repetitions' => ['k', 'r', 101, null, 'The quantity of repetitions of a craft is 1 to 100; it is 101.'];
        yield 'evolved item' => ['k', 'r', 1, 'Egg', 'The code of an item'];
    }

    #[DataProvider('invalidCrafts')]
    public function testACraftOutOfBoundsIsRefused(string $key, string $recipe, int $times, ?string $evolved, string $message): void
    {
        $this->expectExceptionMessageIsOrContains($message);

        new Craft($key, ResourceReference::of('member', '1'), $recipe, $times, [], [], [], [], new DateTimeImmutable(), $evolved);
    }

    public function testProtectedItems(): void
    {
        $policy = new ProtectedItems(['gold-die', 'premium-skin']);

        self::assertFalse($policy->mayConsume('gold-die'));
        self::assertTrue($policy->mayConsume('wood'));

        $this->expectExceptionMessageIsOrContains('The code of a protected item must be');
        new ProtectedItems(['Gold']);
    }
}
