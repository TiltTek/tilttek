<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Crafting\Contract\Inventory;
use Tilttek\Crafting\Craft;
use Tilttek\Crafting\Crafted;
use Tilttek\Crafting\Crafter;
use Tilttek\Crafting\Exception\CraftRefused;
use Tilttek\Crafting\Exception\InvalidRecipe;
use Tilttek\Crafting\Exception\RecipeNotFound;
use Tilttek\Crafting\Holding;
use Tilttek\Crafting\Ingredient;
use Tilttek\Crafting\ItemQuantity;
use Tilttek\Crafting\Plan;
use Tilttek\Crafting\Policy\ProtectedItems;
use Tilttek\Crafting\Pool;
use Tilttek\Crafting\RecipeBook;
use Tilttek\Crafting\Requirement;
use Tilttek\Crafting\Selection;
use Tilttek\Crafting\Store\InMemoryInventory;
use Tilttek\Crafting\Tests\Unit\Fixture\Recorder;

#[CoversClass(Crafter::class)]
#[CoversClass(Plan::class)]
#[CoversClass(Pool::class)]
#[CoversClass(Crafted::class)]
#[CoversClass(CraftRefused::class)]
final class CrafterTest extends TestCase
{
    private MockClock $clock;

    private InMemoryInventory $inventory;

    private Recorder $recorder;

    private Crafter $crafter;

    private ResourceReference $ada;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-27 10:00:00', 'UTC');
        $this->inventory = new InMemoryInventory([
            'copper' => ['metal'],
            'iron' => ['metal'],
            'mithril' => ['metal', 'rare-metal'],
            'spring-water' => ['clear-water'],
            'rain-water' => ['clear-water'],
            'gold-die' => ['metal'],
            'ember' => ['fire'],
        ]);
        $this->recorder = new Recorder();
        $this->crafter = new Crafter($this->book(), $this->inventory, $this->clock, [$this->recorder]);
        $this->ada = ResourceReference::of('member', '1');
    }

    public function testAPreviewShowsWhatIsMissing(): void
    {
        $this->inventory->put($this->ada, 'red-herb', 1);
        $this->inventory->put($this->ada, 'rain-water', 3);

        $plan = $this->crafter->preview($this->ada, 'healing-potion');

        self::assertFalse($plan->isCraftable());
        self::assertSame(1, $plan->times);
        self::assertSame($this->ada, $plan->holder);
        self::assertSame('healing-potion', $plan->recipe->code);
        self::assertSame(2, $plan->missingCount());
        self::assertSame(2, $plan->heldCount());
        self::assertSame(['red-herb', 'mortar'], array_map(static fn (Requirement $requirement): string => $requirement->ingredient->label(), $plan->missing()));
        self::assertSame([['red-herb' => 1], ['rain-water' => 1], []], array_map(static fn (Requirement $requirement): array => $requirement->allocation, $plan->requirements));
        self::assertSame([2, 1, 1], array_map(static fn (Requirement $requirement): int => $requirement->needed, $plan->requirements));
        self::assertSame(1, $this->inventory->quantity($this->ada, 'red-herb'));
        self::assertSame([], $this->recorder->events);
    }

    public function testACraftTakesTheIngredientsAndGivesTheOutputs(): void
    {
        $this->inventory->put($this->ada, 'red-herb', 5);
        $this->inventory->put($this->ada, 'spring-water', 1);
        $this->inventory->put($this->ada, 'rain-water', 4);
        $this->inventory->put($this->ada, 'mortar');

        $craft = $this->crafter->craft($this->ada, 'healing-potion', 'craft-1', 2);

        self::assertSame('craft-1', $craft->key);
        self::assertSame(2, $craft->times);
        self::assertSame('healing-potion', $craft->recipe);
        self::assertSame($this->ada, $craft->holder);
        self::assertNull($craft->evolved);
        self::assertEquals($this->clock->now(), $craft->at);
        self::assertEquals([new ItemQuantity('rain-water', 2), new ItemQuantity('red-herb', 4)], $craft->consumed);
        self::assertEquals([new ItemQuantity('mortar')], $craft->used);
        self::assertEquals([new ItemQuantity('empty-bucket', 2)], $craft->returned);
        self::assertEquals([new ItemQuantity('healing-potion', 2)], $craft->produced);
        self::assertSame(1, $this->inventory->quantity($this->ada, 'red-herb'));
        self::assertSame(2, $this->inventory->quantity($this->ada, 'rain-water'));
        self::assertSame(1, $this->inventory->quantity($this->ada, 'spring-water'));
        self::assertSame(1, $this->inventory->quantity($this->ada, 'mortar'));
        self::assertSame(2, $this->inventory->quantity($this->ada, 'healing-potion'));
        self::assertSame(2, $this->inventory->quantity($this->ada, 'empty-bucket'));
        self::assertEquals([new Crafted($this->ada, 'healing-potion', 'craft-1', $craft->at)], $this->recorder->events);
    }

    public function testATagTakesItsItemsInTheOrderOfTheirCodes(): void
    {
        $this->inventory->put($this->ada, 'spring-water', 1);
        $this->inventory->put($this->ada, 'rain-water', 1);

        $plan = $this->crafter->preview($this->ada, 'two-waters');

        self::assertSame(['rain-water' => 1, 'spring-water' => 1], $plan->requirements[0]->allocation);
        self::assertTrue($plan->requirements[0]->isMet());
    }

    public function testTheTagFewestItemsCarryIsServedFirst(): void
    {
        // The blade asks for two metals, then one rare metal. The ancient gem is both and the first metal by code: served
        // in the recipe's order, the metals would take it and leave the rare metal missing.
        $crafter = new Crafter($this->book(), $this->inventoryOf([new Holding('ancient-gem', 1, ['metal', 'rare-metal']), new Holding('iron', 2, ['metal'])]), $this->clock);

        $plan = $crafter->preview($this->ada, 'blade');

        self::assertTrue($plan->isCraftable());
        self::assertSame([['iron' => 2], ['ancient-gem' => 1]], array_map(static fn (Requirement $requirement): array => $requirement->allocation, $plan->requirements));
    }

    public function testTagsServedAsManyItemsKeepTheRecipesOrder(): void
    {
        $this->inventory->put($this->ada, 'iron', 1);
        $this->inventory->put($this->ada, 'copper', 1);

        $plan = $this->crafter->preview($this->ada, 'alloy');

        self::assertSame([['copper' => 1], ['iron' => 1]], array_map(static fn (Requirement $requirement): array => $requirement->allocation, $plan->requirements));
    }

    public function testItemsNamedByCodeAreServedBeforeTags(): void
    {
        $this->inventory->put($this->ada, 'iron', 2);

        $plan = $this->crafter->preview($this->ada, 'nail');

        self::assertSame([['iron' => 1], ['iron' => 1]], array_map(static fn (Requirement $requirement): array => $requirement->allocation, $plan->requirements));
        self::assertTrue($plan->isCraftable());
        self::assertEquals([new ItemQuantity('iron', 2)], $plan->consumed());
    }

    public function testAnItemSetAsideTwiceIsCountedTwice(): void
    {
        // The relic names the mithril, then asks a metal and a rare metal: with two mithril held, the third ingredient
        // finds none left.
        $this->inventory->put($this->ada, 'mithril', 2);

        $plan = $this->crafter->preview($this->ada, 'relic');

        self::assertSame([['mithril' => 1], ['mithril' => 1], []], array_map(static fn (Requirement $requirement): array => $requirement->allocation, $plan->requirements));
        self::assertFalse($plan->isCraftable());
    }

    public function testAMemberChoosesTheItemsOfATag(): void
    {
        $this->inventory->put($this->ada, 'iron', 2);
        $this->inventory->put($this->ada, 'mithril', 1);
        $this->inventory->put($this->ada, 'copper', 3);

        $plan = $this->crafter->preview($this->ada, 'blade', selection: new Selection([0 => ['copper' => 1, 'iron' => 1]]));

        self::assertSame([['copper' => 1, 'iron' => 1], ['mithril' => 1]], array_map(static fn (Requirement $requirement): array => $requirement->allocation, $plan->requirements));
    }

    public function testAChoiceIsServedBeforeTheOtherTags(): void
    {
        $this->inventory->put($this->ada, 'mithril', 3);

        $plan = $this->crafter->preview($this->ada, 'blade', selection: new Selection([1 => ['mithril' => 1]]));

        self::assertSame([['mithril' => 2], ['mithril' => 1]], array_map(static fn (Requirement $requirement): array => $requirement->allocation, $plan->requirements));
    }

    public function testAChoiceIsServedBeforeTagsFewerItemsCarry(): void
    {
        // Only the mithril carries the rare metal, yet the member chose it for the metals: the choice comes first.
        $this->inventory->put($this->ada, 'iron', 1);
        $this->inventory->put($this->ada, 'mithril', 1);

        $plan = $this->crafter->preview($this->ada, 'blade', selection: new Selection([0 => ['iron' => 1, 'mithril' => 1]]));

        self::assertSame([['iron' => 1, 'mithril' => 1], []], array_map(static fn (Requirement $requirement): array => $requirement->allocation, $plan->requirements));
    }

    public function testAChoiceNotHeldIsMissing(): void
    {
        $this->inventory->put($this->ada, 'iron', 2);
        $this->inventory->put($this->ada, 'mithril', 1);

        $plan = $this->crafter->preview($this->ada, 'blade', selection: new Selection([0 => ['iron' => 1, 'tin' => 1]]));

        self::assertSame(['iron' => 1], $plan->requirements[0]->allocation);
        self::assertSame(1, $plan->requirements[0]->missing());
    }

    public function testAChoiceAddsUpToTheIngredient(): void
    {
        $this->inventory->put($this->ada, 'iron', 5);

        $this->expectException(InvalidRecipe::class);
        $this->expectExceptionMessageIsOrContains('The items chosen for the ingredient #metal of the recipe "blade" add up to 3; it needs 4.');

        $this->crafter->preview($this->ada, 'blade', 2, new Selection([0 => ['iron' => 3]]));
    }

    public function testAChosenItemCarriesTheTag(): void
    {
        $this->inventory->put($this->ada, 'red-herb', 2);

        $this->expectExceptionMessageIsOrContains('"red-herb" cannot serve the ingredient #metal of the recipe "blade".');

        $this->crafter->preview($this->ada, 'blade', selection: new Selection([0 => ['red-herb' => 2]]));
    }

    public function testAProtectedItemIsNeverChosen(): void
    {
        $this->expectExceptionMessageIsOrContains('"gold-die" cannot serve the ingredient #metal of the recipe "blade".');

        $this->crafter->preview($this->ada, 'blade', selection: new Selection([0 => ['gold-die' => 2]]));
    }

    public function testAProtectedItemMayServeATool(): void
    {
        $this->inventory->put($this->ada, 'gold-die', 1);
        $this->inventory->put($this->ada, 'wood', 1);

        $plan = $this->crafter->preview($this->ada, 'shrine', selection: new Selection([1 => ['gold-die' => 1]]));

        self::assertTrue($plan->isCraftable());
        self::assertEquals([new ItemQuantity('gold-die')], $plan->used());
        self::assertEquals([new ItemQuantity('wood')], $plan->consumed());
    }

    public function testATagNeverPicksAProtectedItem(): void
    {
        $this->inventory->put($this->ada, 'gold-die', 5);
        $this->inventory->put($this->ada, 'mithril', 1);

        $plan = $this->crafter->preview($this->ada, 'blade');

        self::assertSame([['mithril' => 1], []], array_map(static fn (Requirement $requirement): array => $requirement->allocation, $plan->requirements));
        self::assertSame(1, $plan->requirements[0]->missing());
    }

    public function testASelectionChoosesForTagsOnly(): void
    {
        $this->expectExceptionMessageIsOrContains('A selection chooses items for an ingredient asked by a tag; the ingredient 0 of the recipe "healing-potion" is not one.');

        $this->crafter->preview($this->ada, 'healing-potion', selection: new Selection([0 => ['red-herb' => 2]]));
    }

    public function testASelectionNamesAnIngredientOfTheRecipe(): void
    {
        $this->expectExceptionMessageIsOrContains('the ingredient 5 of the recipe "blade" is not one.');

        $this->crafter->preview($this->ada, 'blade', selection: new Selection([5 => ['iron' => 1]]));
    }

    public function testAnItemServesOneIngredientOnly(): void
    {
        $this->inventory->put($this->ada, 'iron', 1);

        $plan = $this->crafter->preview($this->ada, 'nail');

        self::assertSame([], $plan->requirements[0]->allocation);
        self::assertSame(['iron' => 1], $plan->requirements[1]->allocation);
    }

    public function testAToolIsNeededOnceAndNeverConsumed(): void
    {
        $this->inventory->put($this->ada, 'red-herb', 6);
        $this->inventory->put($this->ada, 'rain-water', 3);
        $this->inventory->put($this->ada, 'mortar', 2);

        $plan = $this->crafter->preview($this->ada, 'healing-potion', 3);

        self::assertTrue($plan->isCraftable());
        self::assertEquals([new ItemQuantity('mortar')], $plan->used());
        self::assertEquals([new ItemQuantity('rain-water', 3), new ItemQuantity('red-herb', 6)], $plan->consumed());
        self::assertEquals([new ItemQuantity('empty-bucket', 3)], $plan->returned());
        self::assertEquals([new ItemQuantity('healing-potion', 3)], $plan->produced());
    }

    public function testAnEvolvingCraftNamesTheItemThatEvolved(): void
    {
        $this->inventory->put($this->ada, 'dragon-egg');
        $this->inventory->put($this->ada, 'ember', 2);

        $craft = $this->crafter->craft($this->ada, 'egg.hatch', 'hatch-1');

        self::assertSame('dragon-egg', $craft->evolved);
        self::assertSame(0, $this->inventory->quantity($this->ada, 'dragon-egg'));
        self::assertSame(1, $this->inventory->quantity($this->ada, 'dragon'));
    }

    public function testACraftIsMadeOncePerKey(): void
    {
        $this->inventory->put($this->ada, 'red-herb', 4);
        $this->inventory->put($this->ada, 'rain-water', 2);
        $this->inventory->put($this->ada, 'mortar');

        $first = $this->crafter->craft($this->ada, 'healing-potion', 'craft-1');
        $this->clock->sleep(60);
        $again = $this->crafter->craft($this->ada, 'healing-potion', 'craft-1');

        self::assertSame($first, $again);
        self::assertSame(2, $this->inventory->quantity($this->ada, 'red-herb'));
        self::assertCount(1, $this->recorder->events);
    }

    public function testAKeyGivenAgainAfterTheItemsAreGoneReturnsItsCraft(): void
    {
        $this->inventory->put($this->ada, 'red-herb', 2);
        $this->inventory->put($this->ada, 'rain-water', 1);
        $this->inventory->put($this->ada, 'mortar');

        $first = $this->crafter->craft($this->ada, 'healing-potion', 'craft-1');

        self::assertSame($first, $this->crafter->craft($this->ada, 'healing-potion', 'craft-1'));
    }

    public function testAKeyMakesOneCraft(): void
    {
        $this->inventory->put($this->ada, 'red-herb', 4);
        $this->inventory->put($this->ada, 'rain-water', 2);
        $this->inventory->put($this->ada, 'mortar');

        $this->crafter->craft($this->ada, 'healing-potion', 'craft-1');

        $this->expectException(CraftRefused::class);
        $this->expectExceptionMessageIsOrContains('The key "craft-1" already made another craft.');

        $this->crafter->craft($this->ada, 'healing-potion', 'craft-1', 2);
    }

    public function testAKeyOfAnotherHolderIsRefused(): void
    {
        $this->inventory->put($this->ada, 'red-herb', 2);
        $this->inventory->put($this->ada, 'rain-water', 1);
        $this->inventory->put($this->ada, 'mortar');

        $this->crafter->craft($this->ada, 'healing-potion', 'craft-1');

        $this->expectExceptionMessageIsOrContains('The key "craft-1" already made another craft.');

        $this->crafter->craft(ResourceReference::of('member', '2'), 'healing-potion', 'craft-1');
    }

    public function testACraftWithItemsMissingIsRefused(): void
    {
        $this->inventory->put($this->ada, 'iron', 1);

        try {
            $this->crafter->craft($this->ada, 'blade', 'blade-1');
            self::fail('A craft was made without its items.');
        } catch (CraftRefused $craftRefused) {
            self::assertSame('The recipe "blade" cannot be crafted: 1 × #metal, 1 × #rare-metal missing.', $craftRefused->getMessage());
        }

        self::assertSame(1, $this->inventory->quantity($this->ada, 'iron'));
        self::assertNull($this->inventory->find('blade-1'));
        self::assertSame([], $this->recorder->events);
    }

    public function testACraftAppliedConcurrentlyUnderTheKeyIsReturned(): void
    {
        $earlier = new Craft('craft-1', $this->ada, 'healing-potion', 1, [], [], [], [], $this->clock->now());
        $inventory = $this->racingInventory($earlier);
        $crafter = new Crafter($this->book(), $inventory, $this->clock, [$this->recorder]);

        self::assertSame($earlier, $crafter->craft($this->ada, 'healing-potion', 'craft-1'));
        self::assertSame([], $this->recorder->events);
    }

    public function testACraftAppliedConcurrentlyUnderTheKeyForAnotherRecipeIsRefused(): void
    {
        $crafter = new Crafter($this->book(), $this->racingInventory(new Craft('craft-1', $this->ada, 'blade', 1, [], [], [], [], $this->clock->now())), $this->clock);

        $this->expectExceptionMessageIsOrContains('The key "craft-1" already made another craft.');

        $crafter->craft($this->ada, 'healing-potion', 'craft-1');
    }

    public function testAKeyRecordedWithoutItsCraftIsRefused(): void
    {
        $crafter = new Crafter($this->book(), $this->racingInventory(null), $this->clock);

        $this->expectExceptionMessageIsOrContains('The key "craft-1" is recorded, yet its craft cannot be read.');

        $crafter->craft($this->ada, 'healing-potion', 'craft-1');
    }

    public function testTheKeyAndTheRepetitionsAreChecked(): void
    {
        try {
            $this->crafter->craft($this->ada, 'healing-potion', 'bad key');
            self::fail('A bad key was taken.');
        } catch (InvalidRecipe $invalidRecipe) {
            self::assertSame('The key of a craft is 1 to 191 visible ASCII characters, without spaces.', $invalidRecipe->getMessage());
        }

        try {
            $this->crafter->craft($this->ada, 'healing-potion', 'k', 101);
            self::fail('Too many repetitions were taken.');
        } catch (InvalidRecipe $invalidRecipe) {
            self::assertSame('The quantity of repetitions of a craft is 1 to 100; it is 101.', $invalidRecipe->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The quantity of repetitions of a craft is 1 to 100; it is 0.');
        $this->crafter->preview($this->ada, 'healing-potion', 0);
    }

    public function testAHundredRepetitions(): void
    {
        $this->inventory->put($this->ada, 'red-herb', 200);
        $this->inventory->put($this->ada, 'rain-water', 100);
        $this->inventory->put($this->ada, 'mortar');

        self::assertSame(100, $this->crafter->craft($this->ada, 'healing-potion', 'k', 100)->times);
    }

    public function testAnUnknownRecipeIsNotFound(): void
    {
        try {
            $this->crafter->preview($this->ada, 'cake');
            self::fail('An unknown recipe was previewed.');
        } catch (RecipeNotFound) {
        }

        $this->expectException(RecipeNotFound::class);
        $this->crafter->craft($this->ada, 'cake', 'k');
    }

    public function testWhatAHolderCanCraftNow(): void
    {
        $this->inventory->put($this->ada, 'iron', 3);
        $this->inventory->put($this->ada, 'copper', 1);

        self::assertSame(['alloy', 'nail'], array_map(static fn (Plan $plan): string => $plan->recipe->code, $this->crafter->craftable($this->ada)));
        self::assertSame([], $this->crafter->craftable(ResourceReference::of('member', '2')));
    }

    public function testItemsHeldInTheExactQuantityAreEnough(): void
    {
        $this->inventory->put($this->ada, 'red-herb', 2);
        $this->inventory->put($this->ada, 'rain-water', 1);
        $this->inventory->put($this->ada, 'mortar', 1);

        self::assertSame(['healing-potion'], array_map(static fn (Plan $plan): string => $plan->recipe->code, $this->crafter->craftable($this->ada)));
    }

    public function testWhatAHolderIsClosestTo(): void
    {
        $this->inventory->put($this->ada, 'iron', 1);
        $this->inventory->put($this->ada, 'red-herb', 1);
        $this->inventory->put($this->ada, 'wood', 1);

        $closest = $this->crafter->closest($this->ada);

        self::assertSame(['alloy', 'nail', 'blade', 'relic', 'healing-potion'], array_map(static fn (Plan $plan): string => $plan->recipe->code, $closest));
        self::assertSame([1, 1, 2, 2, 3], array_map(static fn (Plan $plan): int => $plan->missingCount(), $closest));
        self::assertSame(['alloy'], array_map(static fn (Plan $plan): string => $plan->recipe->code, $this->crafter->closest($this->ada, 1)));
        self::assertSame([], $this->crafter->closest(ResourceReference::of('member', '2')));
    }

    public function testTheClosestAreTenByDefault(): void
    {
        $recipes = [];

        for ($i = 1; $i <= 11; ++$i) {
            $recipes['r'.$i] = ['ingredients' => [['item' => 'wood'], ['item' => 'stone']], 'outputs' => [['item' => 'wall'.$i]]];
        }

        $crafter = new Crafter(RecipeBook::fromArray(['recipes' => $recipes]), $this->inventory, $this->clock);
        $this->inventory->put($this->ada, 'wood');

        self::assertCount(10, $crafter->closest($this->ada));
        self::assertCount(11, $crafter->closest($this->ada, 11));
    }

    public function testTheClosestLeaveOutWhatIsCraftable(): void
    {
        $this->inventory->put($this->ada, 'iron', 2);

        self::assertNotContains('nail', array_map(static fn (Plan $plan): string => $plan->recipe->code, $this->crafter->closest($this->ada, 50)));
    }

    public function testTheClosestAreBounded(): void
    {
        try {
            $this->crafter->closest($this->ada, 0);
            self::fail('No closest recipe was asked.');
        } catch (InvalidRecipe $invalidRecipe) {
            self::assertSame('The closest recipes are 1 to 50; 0 is out of bounds.', $invalidRecipe->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The closest recipes are 1 to 50; 51 is out of bounds.');
        $this->crafter->closest($this->ada, 51);
    }

    public function testTheHoldingsReadAreBounded(): void
    {
        $holdings = array_map(static fn (int $i): Holding => new Holding('i'.$i, 1), range(1, Crafter::MAX_HOLDINGS));
        $crafter = new Crafter($this->book(), $this->inventoryOf($holdings), $this->clock);

        self::assertCount(0, $crafter->craftable($this->ada));

        $crafter = new Crafter($this->book(), $this->inventoryOf([...$holdings, new Holding('one-more', 1)]), $this->clock);

        $this->expectExceptionMessageIsOrContains('The crafter reads at most 10000 holdings of a holder.');
        $crafter->craftable($this->ada);
    }

    public function testAnItemIsHeldOnce(): void
    {
        $crafter = new Crafter($this->book(), $this->inventoryOf([new Holding('iron', 1), new Holding('iron', 2)]), $this->clock);

        $this->expectExceptionMessageIsOrContains('The inventory gives one holding per item; "iron" came twice.');
        $crafter->preview($this->ada, 'nail');
    }

    public function testTheHoldingsAreReadInTheOrderOfTheirCodes(): void
    {
        $crafter = new Crafter($this->book(), $this->inventoryOf([new Holding('spring-water', 1, ['clear-water']), new Holding('rain-water', 1, ['clear-water'])]), $this->clock);

        self::assertSame(['rain-water' => 1], $crafter->preview($this->ada, 'healing-potion')->requirements[1]->allocation);
    }

    public function testThePoolOffersWhatMayServeAnIngredient(): void
    {
        $book = $this->book();
        $pool = Pool::of([
            'gold-die' => new Holding('gold-die', 1, ['metal']),
            'iron' => new Holding('iron', 1, ['metal']),
        ], $book);

        self::assertSame(['iron'], $pool->candidates(Ingredient::tagged('metal')));
        self::assertSame(['gold-die', 'iron'], $pool->candidates(Ingredient::tagged('metal', 1, false)));
        self::assertSame(['iron'], $pool->candidates(Ingredient::item('iron')));
        self::assertSame([], $pool->candidates(Ingredient::item('copper')));
        self::assertSame([], $pool->candidates(Ingredient::item('copper', 1, false)));
        self::assertSame([], $pool->candidates(Ingredient::tagged('herb')));
    }

    private function book(): RecipeBook
    {
        return RecipeBook::fromArray(['recipes' => [
            'healing-potion' => [
                'ingredients' => [
                    ['item' => 'red-herb', 'quantity' => 2],
                    ['tag' => 'clear-water'],
                    ['item' => 'mortar', 'consumed' => false],
                ],
                'outputs' => [['item' => 'healing-potion']],
                'returns' => [['item' => 'empty-bucket']],
            ],
            'two-waters' => [
                'ingredients' => [['tag' => 'clear-water', 'quantity' => 2]],
                'outputs' => [['item' => 'mixed-water']],
            ],
            'blade' => [
                'ingredients' => [['tag' => 'metal', 'quantity' => 2], ['tag' => 'rare-metal']],
                'outputs' => [['item' => 'blade']],
            ],
            'alloy' => [
                'ingredients' => [['tag' => 'metal'], ['tag' => 'metal', 'quantity' => 1, 'consumed' => false]],
                'outputs' => [['item' => 'alloy']],
            ],
            'nail' => [
                'ingredients' => [['tag' => 'metal'], ['item' => 'iron']],
                'outputs' => [['item' => 'nail', 'quantity' => 10]],
            ],
            'shrine' => [
                'ingredients' => [['item' => 'wood'], ['tag' => 'metal', 'consumed' => false]],
                'outputs' => [['item' => 'shrine']],
            ],
            'relic' => [
                'ingredients' => [['item' => 'mithril'], ['tag' => 'metal'], ['tag' => 'rare-metal']],
                'outputs' => [['item' => 'relic']],
            ],
            'egg.hatch' => [
                'kind' => 'evolve',
                'ingredients' => [['item' => 'dragon-egg'], ['tag' => 'fire', 'quantity' => 2]],
                'outputs' => [['item' => 'dragon']],
            ],
        ]], new ProtectedItems(['gold-die']));
    }

    private function racingInventory(?Craft $recorded): Inventory
    {
        return new class($recorded) implements Inventory {
            private bool $applied = false;

            public function __construct(private readonly ?Craft $recorded)
            {
            }

            public function holdings(ResourceReference $holder): iterable
            {
                return [new Holding('red-herb', 2), new Holding('rain-water', 1, ['clear-water']), new Holding('mortar', 1)];
            }

            public function find(string $key): ?Craft
            {
                return $this->applied ? $this->recorded : null;
            }

            public function apply(Craft $craft): bool
            {
                $this->applied = true;

                return false;
            }
        };
    }

    /**
     * @param list<Holding> $holdings
     */
    private function inventoryOf(array $holdings): Inventory
    {
        return new readonly class($holdings) implements Inventory {
            /**
             * @param list<Holding> $holdings
             */
            public function __construct(private array $holdings)
            {
            }

            public function holdings(ResourceReference $holder): iterable
            {
                yield from $this->holdings;
            }

            public function find(string $key): ?Craft
            {
                return null;
            }

            public function apply(Craft $craft): bool
            {
                return true;
            }
        };
    }
}
