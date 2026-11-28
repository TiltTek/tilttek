<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Crafting\Exception\InvalidRecipe;
use Tilttek\Crafting\Exception\RecipeNotFound;
use Tilttek\Crafting\Ingredient;
use Tilttek\Crafting\ItemQuantity;
use Tilttek\Crafting\Policy\ProtectedItems;
use Tilttek\Crafting\Recipe;
use Tilttek\Crafting\RecipeBook;
use Tilttek\Crafting\RecipeKind;

#[CoversClass(RecipeBook::class)]
#[CoversClass(RecipeNotFound::class)]
final class RecipeBookTest extends TestCase
{
    private const array DEFINITION = [
        'recipes' => [
            'healing-potion' => [
                'ingredients' => [
                    ['item' => 'red-herb', 'quantity' => 2],
                    ['tag' => 'clear-water'],
                    ['item' => 'mortar', 'consumed' => false],
                ],
                'outputs' => [['item' => 'healing-potion']],
                'returns' => [['item' => 'empty-bucket']],
                'attributes' => ['minutes' => 10],
            ],
            'egg.hatch' => [
                'kind' => 'evolve',
                'ingredients' => [['item' => 'dragon-egg'], ['tag' => 'fire', 'quantity' => 2]],
                'outputs' => [['item' => 'dragon']],
            ],
        ],
    ];

    public function testABookIsReadFromPlainValues(): void
    {
        $book = RecipeBook::fromArray(self::DEFINITION);

        self::assertSame(2, $book->count());
        self::assertTrue($book->has('egg.hatch'));
        self::assertFalse($book->has('egg.boil'));
        self::assertSame(['healing-potion', 'egg.hatch'], array_map(static fn (Recipe $recipe): string => $recipe->code, $book->recipes()));

        $potion = $book->recipe('healing-potion');
        self::assertSame(RecipeKind::Combine, $potion->kind);
        self::assertEquals([Ingredient::item('red-herb', 2), Ingredient::tagged('clear-water'), Ingredient::item('mortar', 1, false)], $potion->ingredients);
        self::assertEquals([new ItemQuantity('healing-potion')], $potion->outputs);
        self::assertEquals([new ItemQuantity('empty-bucket')], $potion->returns);
        self::assertSame(['minutes' => 10], $potion->attributes);
        self::assertSame(RecipeKind::Evolve, $book->recipe('egg.hatch')->kind);
        self::assertSame([], $book->recipe('egg.hatch')->returns);
        self::assertSame([], $book->recipe('egg.hatch')->attributes);
    }

    public function testABookWritesItselfBack(): void
    {
        $book = RecipeBook::fromArray(self::DEFINITION);
        $written = $book->toArray();

        self::assertSame([
            'kind' => 'combine',
            'ingredients' => [
                ['item' => 'red-herb', 'quantity' => 2, 'consumed' => true],
                ['tag' => 'clear-water', 'quantity' => 1, 'consumed' => true],
                ['item' => 'mortar', 'quantity' => 1, 'consumed' => false],
            ],
            'outputs' => [['item' => 'healing-potion', 'quantity' => 1]],
            'returns' => [['item' => 'empty-bucket', 'quantity' => 1]],
            'attributes' => ['minutes' => 10],
        ], $written['recipes']['healing-potion']);
        self::assertSame($written, RecipeBook::fromArray($written)->toArray());
    }

    public function testACodeOfDigitsIsRefused(): void
    {
        try {
            RecipeBook::fromArray(['recipes' => ['42' => ['ingredients' => [['item' => 'log']], 'outputs' => [['item' => 'plank']]]]]);
            self::fail('A code of digits was taken.');
        } catch (InvalidRecipe $invalidRecipe) {
            self::assertSame('The recipes of a book are a map of codes to recipes.', $invalidRecipe->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The attributes of the recipe "plank" are a map of at most 10 names');
        RecipeBook::fromArray(['recipes' => ['plank' => ['ingredients' => [['item' => 'log']], 'outputs' => [['item' => 'plank']], 'attributes' => ['9' => 'x']]]]);
    }

    public function testAnEmptyBook(): void
    {
        self::assertSame(0, RecipeBook::fromArray([])->count());
        self::assertSame(['recipes' => []], RecipeBook::fromArray(['recipes' => []])->toArray());
    }

    public function testAMissingRecipeIsNotFound(): void
    {
        $this->expectException(RecipeNotFound::class);
        $this->expectExceptionMessageIsOrContains('The recipe book holds no recipe "egg.boil".');

        RecipeBook::fromArray(self::DEFINITION)->recipe('egg.boil');
    }

    public function testTheRecipesConsumingOrProducingAnItem(): void
    {
        $book = new RecipeBook([
            new Recipe('plank', [Ingredient::item('log')], [new ItemQuantity('plank', 4)]),
            new Recipe('stick', [Ingredient::item('plank'), Ingredient::item('saw', 1, false)], [new ItemQuantity('stick', 2)]),
            new Recipe('chair', [Ingredient::item('plank', 4), Ingredient::item('stick', 4)], [new ItemQuantity('chair')]),
            new Recipe('sawdust', [Ingredient::item('log')], [new ItemQuantity('stick'), new ItemQuantity('sawdust')]),
        ]);

        self::assertSame(['stick', 'chair'], array_map(static fn (Recipe $recipe): string => $recipe->code, $book->consuming('plank')));
        self::assertSame([], $book->consuming('saw'));
        self::assertSame(['stick', 'sawdust'], array_map(static fn (Recipe $recipe): string => $recipe->code, $book->producing('stick')));
        self::assertSame([], $book->producing('log'));
    }

    public function testABookHoldsAThousandRecipesAtMost(): void
    {
        $recipes = array_map(static fn (int $i): Recipe => new Recipe('r'.$i, [Ingredient::item('wood')], [new ItemQuantity('plank')]), range(1, 1000));
        self::assertSame(1000, new RecipeBook($recipes)->count());

        $this->expectExceptionMessageIsOrContains('A recipe book holds at most 1000 recipes; this one has 1001.');
        new RecipeBook([...$recipes, new Recipe('r1001', [Ingredient::item('wood')], [new ItemQuantity('plank')])]);
    }

    public function testAPlainBookHoldsAThousandRecipesAtMost(): void
    {
        $recipes = [];
        $plain = ['ingredients' => [['item' => 'wood']], 'outputs' => [['item' => 'plank']]];

        for ($i = 1; $i <= 1000; ++$i) {
            $recipes['r'.$i] = $plain;
        }

        self::assertSame(1000, RecipeBook::fromArray(['recipes' => $recipes])->count());

        $recipes = [];

        for ($i = 1; $i <= 1001; ++$i) {
            $recipes['r'.$i] = [];
        }

        $this->expectExceptionMessageIsOrContains('A recipe book holds at most 1000 recipes; this one has 1001.');
        RecipeBook::fromArray(['recipes' => $recipes]);
    }

    public function testARecipeIsHeldOnce(): void
    {
        $this->expectExceptionMessageIsOrContains('The recipe book holds the recipe "plank" twice.');

        new RecipeBook([
            new Recipe('plank', [Ingredient::item('log')], [new ItemQuantity('plank')]),
            new Recipe('plank', [Ingredient::item('log')], [new ItemQuantity('plank', 2)]),
        ]);
    }

    public function testTheConsumptionPolicyProtectsItems(): void
    {
        $policy = new ProtectedItems(['gold-die']);
        $book = new RecipeBook([new Recipe('shrine', [Ingredient::item('wood'), Ingredient::item('gold-die', 1, false)], [new ItemQuantity('shrine')])], $policy);

        self::assertSame($policy, $book->policy);
        self::assertFalse($book->mayConsume('gold-die'));
        self::assertTrue($book->mayConsume('wood'));
        self::assertTrue(new RecipeBook([])->mayConsume('gold-die'));
        self::assertNull(new RecipeBook([])->policy);

        $this->expectException(InvalidRecipe::class);
        $this->expectExceptionMessageIsOrContains('The recipe "melt" consumes "gold-die", which no craft may consume.');
        RecipeBook::fromArray(['recipes' => ['melt' => ['ingredients' => [['item' => 'gold-die']], 'outputs' => [['item' => 'gold']]]]], $policy);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalidDefinitions(): iterable
    {
        $outputs = [['item' => 'plank']];
        $ingredients = [['item' => 'log']];

        yield 'unknown key of the book' => [['recipe' => []], 'A recipe book has no "recipe"; it knows recipes.'];
        yield 'recipes not a map' => [['recipes' => 'plank'], 'The recipes of a book are a map of codes to recipes.'];
        yield 'recipe not a map' => [['recipes' => ['plank' => 'log']], 'The recipes of a book are a map of codes to recipes.'];
        yield 'unknown key of a recipe' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => $outputs, 'time' => 3]]], 'The recipe "plank" has no "time"; it knows kind, ingredients, outputs, returns, attributes.'];
        yield 'unknown kind' => [['recipes' => ['plank' => ['kind' => 'random', 'ingredients' => $ingredients, 'outputs' => $outputs]]], 'The kind of the recipe "plank" is "combine" or "evolve".'];
        yield 'kind not a text' => [['recipes' => ['plank' => ['kind' => 1, 'ingredients' => $ingredients, 'outputs' => $outputs]]], 'The kind of the recipe "plank" is "combine" or "evolve".'];
        yield 'no ingredients' => [['recipes' => ['plank' => ['outputs' => $outputs]]], 'The ingredients of the recipe "plank" are a list of at most 16 entries.'];
        yield 'ingredients not a list' => [['recipes' => ['plank' => ['ingredients' => ['a' => ['item' => 'log']], 'outputs' => $outputs]]], 'The ingredients of the recipe "plank" are a list of at most 16 entries.'];
        yield 'seventeen ingredients' => [['recipes' => ['plank' => ['ingredients' => array_fill(0, 17, ['item' => 'log']), 'outputs' => $outputs]]], 'The ingredients of the recipe "plank" are a list of at most 16 entries.'];
        yield 'ingredient not a map' => [['recipes' => ['plank' => ['ingredients' => ['log'], 'outputs' => $outputs]]], 'The ingredients of the recipe "plank" are a list of at most 16 entries.'];
        yield 'unknown key of an ingredient' => [['recipes' => ['plank' => ['ingredients' => [['item' => 'log', 'tool' => true]], 'outputs' => $outputs]]], 'An ingredient of the recipe "plank" has no "tool"; it knows item, tag, quantity, consumed.'];
        yield 'quantity not an integer' => [['recipes' => ['plank' => ['ingredients' => [['item' => 'log', 'quantity' => '2']], 'outputs' => $outputs]]], 'A quantity of the recipe "plank" is an integer.'];
        yield 'consumed not a boolean' => [['recipes' => ['plank' => ['ingredients' => [['item' => 'log', 'consumed' => 1]], 'outputs' => $outputs]]], 'Whether an ingredient of the recipe "plank" is consumed is true or false.'];
        yield 'item and tag' => [['recipes' => ['plank' => ['ingredients' => [['item' => 'log', 'tag' => 'wood']], 'outputs' => $outputs]]], 'An ingredient of the recipe "plank" names either an item or a tag.'];
        yield 'neither item nor tag' => [['recipes' => ['plank' => ['ingredients' => [['quantity' => 2]], 'outputs' => $outputs]]], 'An ingredient of the recipe "plank" names either an item or a tag.'];
        yield 'item not a text' => [['recipes' => ['plank' => ['ingredients' => [['item' => 3]], 'outputs' => $outputs]]], 'An ingredient of the recipe "plank" names either an item or a tag.'];
        yield 'tag not a text' => [['recipes' => ['plank' => ['ingredients' => [['tag' => false]], 'outputs' => $outputs]]], 'An ingredient of the recipe "plank" names either an item or a tag.'];
        yield 'no outputs' => [['recipes' => ['plank' => ['ingredients' => $ingredients]]], 'The outputs of the recipe "plank" are a list of at most 8 entries.'];
        yield 'nine outputs' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => array_fill(0, 9, ['item' => 'plank'])]]], 'The outputs of the recipe "plank" are a list of at most 8 entries.'];
        yield 'nine returns' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => $outputs, 'returns' => array_fill(0, 9, ['item' => 'dust'])]]], 'The returns of the recipe "plank" are a list of at most 8 entries.'];
        yield 'unknown key of an output' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => [['item' => 'plank', 'odds' => 5]]]]], 'An entry of the outputs of the recipe "plank" has no "odds"; it knows item, quantity.'];
        yield 'output without an item' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => [['quantity' => 2]]]]], 'An entry of the outputs of the recipe "plank" names an item.'];
        yield 'return quantity not an integer' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => $outputs, 'returns' => [['item' => 'dust', 'quantity' => 1.5]]]]], 'A quantity of the recipe "plank" is an integer.'];
        yield 'attributes not a map' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => $outputs, 'attributes' => 'slow']]], 'The attributes of the recipe "plank" are a map of at most 10 names to texts, integers or booleans.'];
        yield 'eleven attributes' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => $outputs, 'attributes' => array_fill_keys(array_map(static fn (int $i): string => 'a'.$i, range(1, 11)), 1)]]], 'The attributes of the recipe "plank" are a map of at most 10 names'];
        yield 'attribute of a float' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => $outputs, 'attributes' => ['minutes' => 1.5]]]], 'The attributes of the recipe "plank" are a map of at most 10 names'];
        yield 'attribute of a list' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => $outputs, 'attributes' => ['tags' => []]]]], 'The attributes of the recipe "plank" are a map of at most 10 names'];
        yield 'attribute of null' => [['recipes' => ['plank' => ['ingredients' => $ingredients, 'outputs' => $outputs, 'attributes' => ['note' => null]]]], 'The attributes of the recipe "plank" are a map of at most 10 names'];
    }

    /**
     * @param array<mixed> $definition
     */
    #[DataProvider('invalidDefinitions')]
    public function testAPlainBookOutOfBoundsIsRefused(array $definition, string $message): void
    {
        $this->expectException(InvalidRecipe::class);
        $this->expectExceptionMessageIsOrContains($message);

        RecipeBook::fromArray($definition);
    }

    public function testTheLimitsOfAPlainRecipeAreKept(): void
    {
        $book = RecipeBook::fromArray(['recipes' => ['big' => [
            'ingredients' => array_map(static fn (int $i): array => ['item' => 'i'.$i], range(1, 16)),
            'outputs' => array_map(static fn (int $i): array => ['item' => 'o'.$i], range(1, 8)),
            'returns' => array_map(static fn (int $i): array => ['item' => 'r'.$i], range(1, 8)),
            'attributes' => array_fill_keys(array_map(static fn (int $i): string => 'a'.$i, range(1, 10)), true),
        ]]]);

        self::assertCount(16, $book->recipe('big')->ingredients);
        self::assertCount(8, $book->recipe('big')->outputs);
        self::assertCount(8, $book->recipe('big')->returns);
        self::assertCount(10, $book->recipe('big')->attributes);
    }
}
