<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Crafting\Craft;
use Tilttek\Crafting\Exception\CraftRefused;
use Tilttek\Crafting\Exception\InvalidRecipe;
use Tilttek\Crafting\Holding;
use Tilttek\Crafting\ItemQuantity;
use Tilttek\Crafting\Store\InMemoryInventory;

#[CoversClass(InMemoryInventory::class)]
final class InMemoryInventoryTest extends TestCase
{
    private InMemoryInventory $inventory;

    private ResourceReference $ada;

    private ResourceReference $bo;

    protected function setUp(): void
    {
        $this->inventory = new InMemoryInventory(['iron' => ['metal'], 'mithril' => ['rare-metal', 'metal']]);
        $this->ada = ResourceReference::of('member', '1');
        $this->bo = ResourceReference::of('member', '2');
    }

    public function testItemsArePutAndReadInTheOrderOfTheirCodes(): void
    {
        $this->inventory->put($this->ada, 'mithril');
        $this->inventory->put($this->ada, 'iron', 2);
        $this->inventory->put($this->ada, 'iron', 3);
        $this->inventory->put($this->bo, 'wood');

        self::assertEquals([new Holding('iron', 5, ['metal']), new Holding('mithril', 1, ['metal', 'rare-metal'])], [...$this->inventory->holdings($this->ada)]);
        self::assertSame(5, $this->inventory->quantity($this->ada, 'iron'));
        self::assertSame(0, $this->inventory->quantity($this->ada, 'wood'));
        self::assertEquals([new Holding('wood', 1)], [...$this->inventory->holdings($this->bo)]);
        self::assertSame([], [...$this->inventory->holdings(ResourceReference::of('member', '3'))]);
    }

    public function testWhatIsPutIsChecked(): void
    {
        try {
            $this->inventory->put($this->ada, 'Iron');
            self::fail('A bad item was put.');
        } catch (InvalidRecipe $invalidRecipe) {
            self::assertStringStartsWith('The code of an item', $invalidRecipe->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The quantity of "iron" put in an inventory is 1 to 1000000000; it is 0.');
        $this->inventory->put($this->ada, 'iron', 0);
    }

    public function testACraftIsAppliedOnce(): void
    {
        $this->inventory->put($this->ada, 'iron', 3);
        $this->inventory->put($this->ada, 'hammer');

        $craft = $this->craft('k1', [new ItemQuantity('iron', 3)], [new ItemQuantity('hammer')], [new ItemQuantity('scrap')], [new ItemQuantity('nail', 10)]);

        self::assertTrue($this->inventory->apply($craft));
        self::assertSame($craft, $this->inventory->find('k1'));
        self::assertNull($this->inventory->find('k2'));
        self::assertEquals([new Holding('hammer', 1), new Holding('nail', 10), new Holding('scrap', 1)], [...$this->inventory->holdings($this->ada)]);

        self::assertFalse($this->inventory->apply($craft));
        self::assertSame(10, $this->inventory->quantity($this->ada, 'nail'));
    }

    public function testACraftAddsToWhatIsHeld(): void
    {
        $this->inventory->put($this->ada, 'iron', 2);
        $this->inventory->put($this->ada, 'nail', 1);

        $this->inventory->apply($this->craft('k1', [new ItemQuantity('iron')], [], [], [new ItemQuantity('nail', 2)]));

        self::assertSame(1, $this->inventory->quantity($this->ada, 'iron'));
        self::assertSame(3, $this->inventory->quantity($this->ada, 'nail'));
    }

    public function testACraftAddsEveryOutput(): void
    {
        $this->inventory->put($this->ada, 'iron');

        $this->inventory->apply($this->craft('k1', [new ItemQuantity('iron')], [], [new ItemQuantity('scrap')], [new ItemQuantity('nail', 2), new ItemQuantity('rivet', 3)]));

        self::assertSame(2, $this->inventory->quantity($this->ada, 'nail'));
        self::assertSame(3, $this->inventory->quantity($this->ada, 'rivet'));
        self::assertSame(1, $this->inventory->quantity($this->ada, 'scrap'));
    }

    public function testACraftWithoutItsItemsChangesNothing(): void
    {
        $this->inventory->put($this->ada, 'iron', 2);

        try {
            $this->inventory->apply($this->craft('k1', [new ItemQuantity('iron', 2)], [new ItemQuantity('iron')], [], [new ItemQuantity('nail')]));
            self::fail('A craft took more than was held.');
        } catch (CraftRefused $craftRefused) {
            self::assertSame('The craft "k1" needs 3 × "iron"; the holder has 2.', $craftRefused->getMessage());
        }

        self::assertSame(2, $this->inventory->quantity($this->ada, 'iron'));
        self::assertSame(0, $this->inventory->quantity($this->ada, 'nail'));
        self::assertNull($this->inventory->find('k1'));

        $this->expectExceptionMessageIsOrContains('The craft "k2" needs 1 × "hammer"; the holder has 0.');
        $this->inventory->apply($this->craft('k2', [], [new ItemQuantity('hammer')], [], [new ItemQuantity('nail')]));
    }

    public function testAHolderIsForgotten(): void
    {
        $this->inventory->put($this->ada, 'iron');
        $this->inventory->put($this->bo, 'iron');
        $this->inventory->apply($this->craft('k1', [new ItemQuantity('iron')], [], [], [new ItemQuantity('nail')]));
        $this->inventory->apply(new Craft('k2', $this->bo, 'nail', 1, [new ItemQuantity('iron')], [], [], [new ItemQuantity('nail')], new DateTimeImmutable()));

        $this->inventory->forget($this->ada);

        self::assertSame([], [...$this->inventory->holdings($this->ada)]);
        self::assertNull($this->inventory->find('k1'));
        self::assertNotNull($this->inventory->find('k2'));
        self::assertSame(1, $this->inventory->quantity($this->bo, 'nail'));
    }

    /**
     * @param list<ItemQuantity> $consumed
     * @param list<ItemQuantity> $used
     * @param list<ItemQuantity> $returned
     * @param list<ItemQuantity> $produced
     */
    private function craft(string $key, array $consumed, array $used, array $returned, array $produced): Craft
    {
        return new Craft($key, $this->ada, 'nail', 1, $consumed, $used, $returned, $produced, new DateTimeImmutable('2026-11-27 10:00:00'));
    }
}
