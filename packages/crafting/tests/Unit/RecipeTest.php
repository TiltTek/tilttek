<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Crafting\Exception\InvalidRecipe;
use Tilttek\Crafting\Ingredient;
use Tilttek\Crafting\ItemQuantity;
use Tilttek\Crafting\Recipe;
use Tilttek\Crafting\RecipeKind;

#[CoversClass(Recipe::class)]
#[CoversClass(RecipeKind::class)]
final class RecipeTest extends TestCase
{
    public function testARecipeCombines(): void
    {
        $recipe = new Recipe(
            'healing-potion',
            [Ingredient::item('red-herb', 2), Ingredient::tagged('clear-water'), Ingredient::item('mortar', 1, false)],
            [new ItemQuantity('healing-potion')],
            [new ItemQuantity('empty-bucket')],
            attributes: ['minutes' => 10, 'workshop' => 'Alchemy bench', 'secret' => false],
        );

        self::assertSame(RecipeKind::Combine, $recipe->kind);
        self::assertNull($recipe->evolves());
        self::assertTrue($recipe->consumes('red-herb'));
        self::assertFalse($recipe->consumes('mortar'));
        self::assertFalse($recipe->consumes('clear-water'));
        self::assertFalse($recipe->consumes('healing-potion'));
        self::assertTrue($recipe->produces('healing-potion'));
        self::assertFalse($recipe->produces('empty-bucket'));
        self::assertSame(['minutes' => 10, 'workshop' => 'Alchemy bench', 'secret' => false], $recipe->attributes);
    }

    public function testARecipeEvolves(): void
    {
        $recipe = new Recipe('egg.hatch', [Ingredient::item('dragon-egg'), Ingredient::tagged('fire', 2)], [new ItemQuantity('dragon')], kind: RecipeKind::Evolve);

        self::assertSame('dragon-egg', $recipe->evolves());
        self::assertSame([], $recipe->returns);
    }

    public function testARecipeTakesItsLimits(): void
    {
        $ingredients = array_map(static fn (int $i): Ingredient => Ingredient::item('i'.$i), range(1, 16));
        $outputs = array_map(static fn (int $i): ItemQuantity => new ItemQuantity('o'.$i, 999), range(1, 8));
        $returns = array_map(static fn (int $i): ItemQuantity => new ItemQuantity('r'.$i), range(1, 8));
        $attributes = array_fill_keys(array_map(static fn (int $i): string => 'a'.$i, range(1, 10)), str_repeat('é', 100));

        $recipe = new Recipe('big', $ingredients, $outputs, $returns, attributes: $attributes);

        self::assertCount(16, $recipe->ingredients);
        self::assertCount(8, $recipe->outputs);
        self::assertCount(8, $recipe->returns);
        self::assertCount(10, $recipe->attributes);
    }

    /**
     * @return iterable<string, array{Closure(): Recipe, string}>
     */
    public static function invalidRecipes(): iterable
    {
        $wood = [Ingredient::item('wood')];
        $plank = [new ItemQuantity('plank')];

        yield 'code' => [static fn (): Recipe => new Recipe('Plank', $wood, $plank), 'The code of a recipe must be'];
        yield 'no ingredient' => [static fn (): Recipe => new Recipe('plank', [], $plank), 'The recipe "plank" lists 1 to 16 ingredients; it has 0.'];
        yield 'seventeen ingredients' => [static fn (): Recipe => new Recipe('plank', array_map(static fn (int $i): Ingredient => Ingredient::item('i'.$i), range(1, 17)), $plank), 'The recipe "plank" lists 1 to 16 ingredients; it has 17.'];
        yield 'twice the same item' => [static fn (): Recipe => new Recipe('plank', [Ingredient::item('wood'), Ingredient::item('wood', 2)], $plank), 'The recipe "plank" names the ingredient wood twice.'];
        yield 'twice the same tool' => [static fn (): Recipe => new Recipe('plank', [Ingredient::item('wood'), Ingredient::item('saw', 1, false), Ingredient::item('saw', 2, false)], $plank), 'The recipe "plank" names the ingredient saw as a tool twice.'];
        yield 'twice the same tag' => [static fn (): Recipe => new Recipe('plank', [Ingredient::tagged('log'), Ingredient::tagged('log', 2)], $plank), 'The recipe "plank" names the ingredient #log twice.'];
        yield 'tools only' => [static fn (): Recipe => new Recipe('plank', [Ingredient::item('saw', 1, false), Ingredient::tagged('bench', 1, false)], $plank), 'The recipe "plank" consumes at least one ingredient; tools alone produce nothing.'];
        yield 'no output' => [static fn (): Recipe => new Recipe('plank', $wood, []), 'The recipe "plank" lists 1 to 8 outputs; it has 0.'];
        yield 'nine outputs' => [static fn (): Recipe => new Recipe('plank', $wood, array_map(static fn (int $i): ItemQuantity => new ItemQuantity('o'.$i), range(1, 9))), 'The recipe "plank" lists 1 to 8 outputs; it has 9.'];
        yield 'an output above 999' => [static fn (): Recipe => new Recipe('plank', $wood, [new ItemQuantity('plank', 1000)]), 'The quantity of "plank" in the recipe "plank" is 1 to 999; it is 1000.'];
        yield 'twice the same output' => [static fn (): Recipe => new Recipe('plank', $wood, [new ItemQuantity('plank'), new ItemQuantity('plank', 2)]), 'The recipe "plank" names "plank" twice among its outputs.'];
        yield 'nine returns' => [static fn (): Recipe => new Recipe('plank', $wood, $plank, array_map(static fn (int $i): ItemQuantity => new ItemQuantity('r'.$i), range(1, 9))), 'The recipe "plank" lists 0 to 8 returns; it has 9.'];
        yield 'twice the same return' => [static fn (): Recipe => new Recipe('plank', $wood, $plank, [new ItemQuantity('dust'), new ItemQuantity('dust')]), 'The recipe "plank" names "dust" twice among its returns.'];
        yield 'eleven attributes' => [static fn (): Recipe => new Recipe('plank', $wood, $plank, attributes: array_fill_keys(array_map(static fn (int $i): string => 'a'.$i, range(1, 11)), 1)), 'A recipe carries at most 10 attributes; "plank" has 11.'];
        yield 'attribute name' => [static fn (): Recipe => new Recipe('plank', $wood, $plank, attributes: ['Name' => 1]), 'The code of an attribute must be'];
        yield 'empty attribute' => [static fn (): Recipe => new Recipe('plank', $wood, $plank, attributes: ['note' => '']), 'The attribute "note" is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.'];
        yield 'long attribute' => [static fn (): Recipe => new Recipe('plank', $wood, $plank, attributes: ['note' => str_repeat('a', 101)]), 'The attribute "note" is 1 to 100'];
        yield 'attribute with a control character' => [static fn (): Recipe => new Recipe('plank', $wood, $plank, attributes: ['note' => "a\tb"]), 'The attribute "note" is 1 to 100'];
        yield 'attribute with spaces around' => [static fn (): Recipe => new Recipe('plank', $wood, $plank, attributes: ['note' => ' ab']), 'The attribute "note" is 1 to 100'];
        yield 'attribute ending in a new line' => [static fn (): Recipe => new Recipe('plank', $wood, $plank, attributes: ['note' => "ab\n"]), 'The attribute "note" is 1 to 100'];
        yield 'evolution of a tag' => [static fn (): Recipe => new Recipe('hatch', [Ingredient::tagged('egg')], [new ItemQuantity('dragon')], kind: RecipeKind::Evolve), 'The evolving recipe "hatch" takes its first ingredient, one item named by its code, and consumes it.'];
        yield 'evolution of a tool' => [static fn (): Recipe => new Recipe('hatch', [Ingredient::item('egg', 1, false), Ingredient::item('fire')], [new ItemQuantity('dragon')], kind: RecipeKind::Evolve), 'The evolving recipe "hatch" takes its first ingredient'];
        yield 'evolution of two' => [static fn (): Recipe => new Recipe('hatch', [Ingredient::item('egg', 2)], [new ItemQuantity('dragon')], kind: RecipeKind::Evolve), 'The evolving recipe "hatch" takes its first ingredient'];
        yield 'evolution into two outputs' => [static fn (): Recipe => new Recipe('hatch', [Ingredient::item('egg')], [new ItemQuantity('dragon'), new ItemQuantity('shell')], kind: RecipeKind::Evolve), 'The evolving recipe "hatch" produces one item of another kind than "egg".'];
        yield 'evolution into two of an item' => [static fn (): Recipe => new Recipe('hatch', [Ingredient::item('egg')], [new ItemQuantity('dragon', 2)], kind: RecipeKind::Evolve), 'The evolving recipe "hatch" produces one item'];
        yield 'evolution into itself' => [static fn (): Recipe => new Recipe('hatch', [Ingredient::item('egg')], [new ItemQuantity('egg')], kind: RecipeKind::Evolve), 'The evolving recipe "hatch" produces one item'];
    }

    /**
     * @param Closure(): Recipe $recipe
     */
    #[DataProvider('invalidRecipes')]
    public function testARecipeOutOfBoundsIsRefused(Closure $recipe, string $message): void
    {
        $this->expectException(InvalidRecipe::class);
        $this->expectExceptionMessageIsOrContains($message);

        $recipe();
    }

    public function testAnItemMayBeTakenAndHeldAsATool(): void
    {
        $recipe = new Recipe('forge', [Ingredient::tagged('metal'), Ingredient::tagged('metal', 1, false)], [new ItemQuantity('alloy')]);

        self::assertCount(2, $recipe->ingredients);
    }

    public function testAnAttributeOfTheGreatestLengthIsKept(): void
    {
        $recipe = new Recipe('plank', [Ingredient::item('wood')], [new ItemQuantity('plank')], attributes: ['note' => str_repeat('a', 100), 'a1' => 3]);

        self::assertSame(3, $recipe->attributes['a1']);
    }
}
