<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Trade;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Trade\InMemoryInventory;
use Tilttek\Exchange\Trade\ItemMove;

#[CoversClass(InMemoryInventory::class)]
final class InMemoryInventoryTest extends TestCase
{
    public function testItemsChangeHandsAllOrNone(): void
    {
        $inventory = new InMemoryInventory();
        $inventory->give($this->item(1), $this->member(1));
        $inventory->give($this->item(2), $this->member(2));
        $inventory->give($this->item(3), $this->member(2), false);

        self::assertTrue($inventory->ownerOf($this->item(1))?->equals($this->member(1)));
        self::assertNull($inventory->ownerOf($this->item(9)));
        self::assertTrue($inventory->isTradable($this->item(1)));
        self::assertFalse($inventory->isTradable($this->item(3)));
        self::assertFalse($inventory->isTradable($this->item(9)));

        $inventory->give($this->item(3), $this->member(2));
        self::assertTrue($inventory->isTradable($this->item(3)));
        $inventory->give($this->item(3), $this->member(2), false);

        $inventory->move([new ItemMove($this->item(1), $this->member(1), $this->member(2)), new ItemMove($this->item(2), $this->member(2), $this->member(1))]);
        self::assertTrue($inventory->ownerOf($this->item(1))?->equals($this->member(2)));
        self::assertTrue($inventory->ownerOf($this->item(2))?->equals($this->member(1)));

        foreach ([$this->item(1), $this->item(9)] as $missing) {
            try {
                $inventory->move([new ItemMove($this->item(2), $this->member(1), $this->member(2)), new ItemMove($missing, $this->member(1), $this->member(2))]);
                self::fail('A refusal was expected.');
            } catch (ExchangeRefused $refused) {
                self::assertSame(Refusal::NotOwned, $refused->refusal);
                self::assertSame(\sprintf('"%s" does not belong to "member:1".', $missing->toString()), $refused->getMessage());
                self::assertTrue($inventory->ownerOf($this->item(2))?->equals($this->member(1)));
            }
        }
    }

    private function item(int $id): ResourceReference
    {
        return ResourceReference::of('card', (string) $id);
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    public function testResetLeavesTheStoreAsNew(): void
    {
        $store = new InMemoryInventory();

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $property->setValue($store, \is_int($property->getValue($store)) ? 7 : ['kept']);
        }

        $store->reset();

        self::assertEquals(new InMemoryInventory(), $store);
    }
}
