<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Generator;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Inventory\Burden;
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\Change;
use Tilttek\Inventory\Container;
use Tilttek\Inventory\Exception\InvalidInventory;
use Tilttek\Inventory\Exception\InventoryRefused;
use Tilttek\Inventory\Exception\UnknownInventory;
use Tilttek\Inventory\Inventories;
use Tilttek\Inventory\Inventory;
use Tilttek\Inventory\InventoryChanged;
use Tilttek\Inventory\Item;
use Tilttek\Inventory\Limits;
use Tilttek\Inventory\Load;
use Tilttek\Inventory\LoadBand;
use Tilttek\Inventory\LogEntry;
use Tilttek\Inventory\Measure;
use Tilttek\Inventory\Packing;
use Tilttek\Inventory\Place;
use Tilttek\Inventory\Refusal;
use Tilttek\Inventory\Rules\StaticRuleBook;
use Tilttek\Inventory\Store\InMemoryInventoryStore;
use Tilttek\Inventory\Tests\Unit\Fixture\Recorder;
use Tilttek\Inventory\Tests\Unit\Fixture\StrengthCapacity;

#[CoversClass(Inventories::class)]
#[CoversClass(Packing::class)]
#[CoversClass(InMemoryInventoryStore::class)]
#[CoversClass(InventoryChanged::class)]
final class InventoriesTest extends TestCase
{
    private MockClock $clock;

    private InMemoryInventoryStore $store;

    private Recorder $recorder;

    private Inventories $inventories;

    private ResourceReference $inventory;

    private ResourceReference $party;

    private ResourceReference $ada;

    private ResourceReference $bo;

    private ResourceReference $gm;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-25 10:00:00', 'UTC');
        $this->store = new InMemoryInventoryStore();
        $this->recorder = new Recorder();
        $this->inventory = ResourceReference::of('inventory', '7');
        $this->party = ResourceReference::of('party', '3');
        $this->ada = ResourceReference::of('character', '12');
        $this->bo = ResourceReference::of('character', '13');
        $this->gm = ResourceReference::of('player', '1');
        $this->inventories = $this->service();
    }

    public function testTheQuickStartOfTheReadmeWorks(): void
    {
        $this->openWeighed();
        $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(5), Place::carriedBy($this->ada), new Container(maxWeight: 30));
        $this->inventories->add($this->inventory, 'rope', 'Hempen rope', 1, new Burden(10), Place::inside('pack'));
        $this->inventories->add($this->inventory, 'arrows', 'Arrows', 20, new Burden(1, 1, 20), Place::carriedBy($this->ada));
        $this->inventories->move($this->inventory, 'arrows', Place::carriedBy($this->bo), quantity: 10, as: 'arrows.bo');
        $this->inventories->decrease($this->inventory, 'arrows', 3, reason: 'shot at the goblins');

        $load = $this->inventories->load($this->inventory, $this->ada);
        self::assertSame(22, $load?->carried);
        self::assertSame('unencumbered', $load->band?->key);
        self::assertSame(128, $load->room());
    }

    public function testAnInventoryIsOpenedOnceForItsOwner(): void
    {
        $opened = $this->inventories->open($this->inventory, $this->party, "The party's gear", 'srd-5.2', $this->gm);

        self::assertSame("The party's gear", $opened->name);
        self::assertSame('srd-5.2', $opened->system);
        self::assertSame('party:3', $opened->owner->toString());
        self::assertSame('2026-11-25 10:00:00', $opened->openedAt->format('Y-m-d H:i:s'));
        self::assertSame($opened, $this->inventories->inventory($this->inventory));
        self::assertSame(['inventory:7 opened - player:1'], $this->recorder->summary());

        $this->refused(Refusal::AlreadyOpen, fn (): mixed => $this->inventories->open($this->inventory, $this->party, 'Again'));
    }

    public function testANewInventoryIsCheckedBeforeItsReference(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');

        $this->expectException(InvalidInventory::class);
        $this->inventories->open($this->inventory, $this->party, '');
    }

    public function testAnOwnerHoldsABoundedNumberOfInventories(): void
    {
        $inventories = $this->service(new Limits(inventoriesPerOwner: 2));
        $inventories->open(ResourceReference::of('inventory', '2'), $this->ada, 'Second');

        $this->clock->modify('-1 hour');
        $inventories->open(ResourceReference::of('inventory', '1'), $this->ada, 'First');
        $inventories->open(ResourceReference::of('inventory', '3'), $this->bo, 'Other owner');

        self::assertSame(['First', 'Second'], array_map(static fn (Inventory $inventory): string => $inventory->name, $inventories->inventoriesOf($this->ada)));
        $this->refused(Refusal::TooManyInventories, static fn (): mixed => $inventories->open(ResourceReference::of('inventory', '4'), ResourceReference::of('character', '12'), 'Third'));
        self::assertSame([], $inventories->inventoriesOf($this->gm));
    }

    public function testInventoriesOfTheSameMomentAreReadByReference(): void
    {
        $this->inventories->open(ResourceReference::of('inventory', 'b'), $this->ada, 'B');
        $this->inventories->open(ResourceReference::of('inventory', 'a'), $this->ada, 'A');

        self::assertSame(['A', 'B'], array_map(static fn (Inventory $inventory): string => $inventory->name, $this->inventories->inventoriesOf($this->ada)));
    }

    public function testAnUnknownInventoryIsNamed(): void
    {
        $this->expectException(UnknownInventory::class);
        $this->expectExceptionMessageIsOrContains('No inventory is known as "inventory:7".');

        $this->inventories->items($this->inventory);
    }

    public function testAnInventoryIsRenamedChangesSystemAndCloses(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'rope', 'Rope');

        self::assertSame('Kit', $this->inventories->rename($this->inventory, 'Kit', $this->gm)->name);
        self::assertSame('Kit', $this->inventories->inventory($this->inventory)->name);
        self::assertSame('srd-5.2', $this->inventories->useSystem($this->inventory, 'srd-5.2', $this->gm)->system);
        self::assertSame('srd-5.2', $this->inventories->inventory($this->inventory)->system);

        $this->inventories->close($this->inventory, $this->gm);

        self::assertNull($this->store->inventory('inventory:7'));
        self::assertSame([], $this->store->items('inventory:7'));
        self::assertSame([], $this->store->log('inventory:7', 10));
        self::assertSame([
            'inventory:7 opened - -',
            'inventory:7 added rope -',
            'inventory:7 updated - player:1',
            'inventory:7 updated - player:1',
            'inventory:7 closed - player:1',
        ], $this->recorder->summary());

        $this->expectException(UnknownInventory::class);
        $this->inventories->close($this->inventory);
    }

    public function testItemsAreAddedToTheStashOnACarrierOrInAContainer(): void
    {
        $this->openWeighed();
        $rope = $this->inventories->add($this->inventory, 'rope', 'Rope', 2, new Burden(10), by: $this->gm, reason: 'bought');
        $pack = $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(5), Place::carriedBy($this->ada), new Container(30));
        $this->inventories->add($this->inventory, 'torch', 'Torch', 3, new Burden(1), Place::inside('pack'));

        self::assertTrue($rope->place->isStash());
        self::assertSame(2, $rope->quantity);
        self::assertSame($pack, $this->inventories->item($this->inventory, 'pack'));
        self::assertSame(['pack', 'rope', 'torch'], $this->keys($this->inventories->items($this->inventory)));
        self::assertSame(['torch'], $this->keys($this->inventories->itemsAt($this->inventory, Place::inside('pack'))));
        self::assertSame(['pack'], $this->keys($this->inventories->itemsAt($this->inventory, Place::carriedBy($this->ada))));
        self::assertSame(['rope'], $this->keys($this->inventories->itemsAt($this->inventory, Place::stash())));

        $log = $this->inventories->log($this->inventory);
        self::assertSame(['added torch 3 - container:pack', 'added pack 1 - carrier:character:12', 'added rope 2 - stash', 'opened - - - -'], $this->lines($log));
        self::assertSame('bought', $log[2]->reason);
        self::assertSame('player:1', $log[2]->by);
        self::assertNull($log[0]->by);
    }

    public function testAnItemKeyIsTakenOnce(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'rope', 'Rope');

        $this->refused(Refusal::ItemExists, fn (): mixed => $this->inventories->add($this->inventory, 'rope', 'Rope'));
    }

    public function testAnInventoryHoldsABoundedNumberOfItems(): void
    {
        $inventories = $this->service(new Limits(itemsPerInventory: 2));
        $inventories->open($this->inventory, $this->party, 'Gear');
        $inventories->add($this->inventory, 'a', 'A');
        $inventories->add($this->inventory, 'b', 'B', 2);

        $this->refused(Refusal::TooManyItems, fn (): mixed => $inventories->add($this->inventory, 'c', 'C'));
        $this->refused(Refusal::TooManyItems, fn (): mixed => $inventories->move($this->inventory, 'b', Place::stash(), 1, 'b.part'));
    }

    public function testAnItemGoesOnlyIntoAContainerThatExists(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'rope', 'Rope');

        $this->refused(Refusal::NotAContainer, fn (): mixed => $this->inventories->add($this->inventory, 'torch', 'Torch', 1, new Burden(), Place::inside('rope')));

        $this->expectException(UnknownInventory::class);
        $this->expectExceptionMessageIsOrContains('The inventory "inventory:7" holds no item under the key "pack".');
        $this->inventories->add($this->inventory, 'torch', 'Torch', 1, new Burden(), Place::inside('pack'));
    }

    public function testAReasonIsChecked(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');

        $this->expectException(InvalidInventory::class);
        $this->expectExceptionMessageIsOrContains('A reason is 1 to 200 characters');
        $this->inventories->add($this->inventory, 'rope', 'Rope', reason: '');
    }

    public function testAContainerHoldsWhatItsBoundsAllow(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(5, 1), Place::stash(), new Container(30, 3));
        $this->inventories->add($this->inventory, 'rope', 'Rope', 3, new Burden(10, 1), Place::inside('pack'));

        $fill = $this->inventories->fill($this->inventory, 'pack');
        self::assertSame(30, $fill->weight);
        self::assertSame(3, $fill->slots);
        self::assertSame(30, $fill->maxWeight);
        self::assertSame(3, $fill->maxSlots);
        self::assertSame('pack', $fill->container);

        $this->refused(Refusal::ContainerFull, fn (): mixed => $this->inventories->add($this->inventory, 'feather', 'Feather', 1, new Burden(1), Place::inside('pack')));
        $this->refused(Refusal::ContainerFull, fn (): mixed => $this->inventories->add($this->inventory, 'note', 'Note', 1, new Burden(0, 1), Place::inside('pack')));
        $this->refused(Refusal::ContainerFull, fn (): mixed => $this->inventories->increase($this->inventory, 'rope', 1));
        $this->inventories->add($this->inventory, 'spark', 'Spark', 1, new Burden(), Place::inside('pack'));
        $this->refused(Refusal::NotAContainer, fn (): mixed => $this->inventories->fill($this->inventory, 'rope'));
    }

    public function testANestedContainerCountsWhatItHoldsUnlessItsContentsWeighNothing(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(5, 1), Place::stash(), new Container(20));
        $this->inventories->add($this->inventory, 'pouch', 'Pouch', 1, new Burden(1, 1), Place::inside('pack'), new Container());
        $this->inventories->add($this->inventory, 'gems', 'Gems', 4, new Burden(2, 1, 10), Place::inside('pouch'));
        $this->inventories->add($this->inventory, 'bag', 'Magic bag', 1, new Burden(3, 1), Place::inside('pack'), new Container(100, null, false));
        $this->inventories->add($this->inventory, 'anvil', 'Anvil', 1, new Burden(90, 5), Place::inside('bag'));

        $pack = $this->inventories->fill($this->inventory, 'pack');
        self::assertSame(1 + 8 + 3, $pack->weight);
        self::assertSame(1 + 1 + 1, $pack->slots);
        self::assertSame(8, $this->inventories->fill($this->inventory, 'pouch')->weight);
        self::assertSame(90, $this->inventories->fill($this->inventory, 'bag')->weight);
        self::assertSame(5, $this->inventories->fill($this->inventory, 'bag')->slots);

        // The pouch's contents count against the pack: 9 more would pass 20.
        $this->refused(Refusal::ContainerFull, fn (): mixed => $this->inventories->increase($this->inventory, 'gems', 5));
        $this->inventories->increase($this->inventory, 'gems', 4);
        self::assertSame(20, $this->inventories->fill($this->inventory, 'pack')->weight);
    }

    public function testContainersNestNoDeeperThanTheLimit(): void
    {
        $inventories = $this->service(new Limits(containerDepth: 2));
        $inventories->open($this->inventory, $this->party, 'Gear');
        $inventories->add($this->inventory, 'chest', 'Chest', 1, new Burden(), Place::stash(), new Container());
        $inventories->add($this->inventory, 'box', 'Box', 1, new Burden(), Place::inside('chest'), new Container());
        $inventories->add($this->inventory, 'ring', 'Ring', 1, new Burden(), Place::inside('box'));
        $inventories->add($this->inventory, 'sack', 'Sack', 1, new Burden(), Place::stash(), new Container());
        $inventories->add($this->inventory, 'pouch', 'Pouch', 1, new Burden(), Place::inside('sack'), new Container());

        $inventories->add($this->inventory, 'vial', 'Vial', 1, new Burden(), Place::inside('pouch'), new Container());
        $this->refused(Refusal::TooDeep, fn (): mixed => $inventories->add($this->inventory, 'dust', 'Dust', 1, new Burden(), Place::inside('vial')));
        $inventories->remove($this->inventory, 'vial');
        // The box holds a ring: it goes into the sack, not into the pouch.
        $inventories->move($this->inventory, 'box', Place::inside('sack'));
        $this->refused(Refusal::TooDeep, fn (): mixed => $inventories->move($this->inventory, 'box', Place::inside('pouch')));
        self::assertSame('container:sack', $inventories->item($this->inventory, 'box')->place->toString());
    }

    public function testAContainerNeverGoesInsideItselfOrWhatItHolds(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'chest', 'Chest', 1, new Burden(), Place::stash(), new Container());
        $this->inventories->add($this->inventory, 'box', 'Box', 1, new Burden(), Place::inside('chest'), new Container());
        $this->inventories->add($this->inventory, 'pouch', 'Pouch', 1, new Burden(), Place::inside('box'), new Container());

        $this->refused(Refusal::ContainerCycle, fn (): mixed => $this->inventories->move($this->inventory, 'chest', Place::inside('chest')));
        $this->refused(Refusal::ContainerCycle, fn (): mixed => $this->inventories->move($this->inventory, 'chest', Place::inside('box')));
        $this->refused(Refusal::ContainerCycle, fn (): mixed => $this->inventories->move($this->inventory, 'chest', Place::inside('pouch')));
        $this->refused(Refusal::ContainerCycle, fn (): mixed => $this->inventories->move($this->inventory, 'box', Place::inside('pouch')));

        $this->inventories->move($this->inventory, 'pouch', Place::inside('chest'));
        self::assertSame('container:chest', $this->inventories->item($this->inventory, 'pouch')->place->toString());
    }

    public function testTheLoadOfACarrierFollowsTheRulesOfItsSystem(): void
    {
        $this->openWeighed();
        $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(5), Place::carriedBy($this->ada), new Container());
        $this->inventories->add($this->inventory, 'rope', 'Rope', 4, new Burden(10), Place::inside('pack'));
        $this->inventories->add($this->inventory, 'bag', 'Magic bag', 1, new Burden(1), Place::inside('pack'), new Container(null, null, false));
        $this->inventories->add($this->inventory, 'anvil', 'Anvil', 1, new Burden(90), Place::inside('bag'));
        $this->inventories->add($this->inventory, 'sword', 'Sword', 1, new Burden(3), Place::carriedBy($this->bo));
        $this->inventories->add($this->inventory, 'tent', 'Tent', 1, new Burden(20));

        $load = $this->inventories->load($this->inventory, $this->ada);
        self::assertNotNull($load);
        self::assertSame(46, $load->carried);
        self::assertSame(150, $load->capacity);
        self::assertSame('unencumbered', $load->band?->key);
        self::assertSame(150, $load->limit);
        self::assertSame(Measure::Weight, $load->measure);
        self::assertSame('character:12', $load->carrier->toString());

        $loads = $this->inventories->loads($this->inventory);
        self::assertSame(['character:12', 'character:13'], array_map(static fn (Load $load): string => $load->carrier->toString(), $loads));
        self::assertSame([46, 3], array_map(static fn (Load $load): int => $load->carried, $loads));

        $nobody = $this->inventories->load($this->inventory, $this->gm);
        self::assertSame(0, $nobody?->carried);

        $this->inventories->increase($this->inventory, 'rope', 1);
        self::assertSame('encumbered', $this->inventories->load($this->inventory, $this->ada)?->band?->key);

        $this->inventories->move($this->inventory, 'pack', Place::carriedBy($this->bo));
        self::assertSame(59, $this->inventories->load($this->inventory, $this->bo)?->carried);
        self::assertSame(0, $this->inventories->load($this->inventory, $this->ada)?->carried);
        self::assertSame(['character:13'], array_map(static fn (Load $load): string => $load->carrier->toString(), $this->inventories->loads($this->inventory)));
    }

    public function testACarrierMayGoOverItsCapacityUnlessTheRulesRefuseIt(): void
    {
        $this->openWeighed();
        $this->inventories->add($this->inventory, 'anvil', 'Anvil', 1, new Burden(151), Place::carriedBy($this->ada));

        $load = $this->inventories->load($this->inventory, $this->ada);
        self::assertTrue($load?->isOver());
        self::assertNull($load->band);
        self::assertSame(0, $load->room());

        $refusing = $this->service(rules: new StaticRuleBook(['knave' => new CarryRules(Measure::Slots, 10, [new LoadBand('fine', 1)], true)]));
        $refusing->open(ResourceReference::of('inventory', '8'), $this->ada, 'Knave', 'knave');

        $inventory = ResourceReference::of('inventory', '8');
        $refusing->add($inventory, 'sack', 'Sack', 1, new Burden(0, 1), Place::carriedBy($this->ada), new Container());
        $refusing->add($inventory, 'rations', 'Rations', 9, new Burden(0, 1, 3), Place::inside('sack'));
        $refusing->add($inventory, 'shield', 'Shield', 1, new Burden(0, 6), Place::carriedBy($this->ada));
        self::assertSame(10, $refusing->load($inventory, $this->ada)?->carried);

        $this->refused(Refusal::OverCapacity, static fn (): mixed => $refusing->add($inventory, 'torch', 'Torch', 1, new Burden(0, 1), Place::carriedBy(ResourceReference::of('character', '12'))));
        $this->refused(Refusal::OverCapacity, static fn (): mixed => $refusing->add($inventory, 'torch', 'Torch', 1, new Burden(0, 1), Place::inside('sack')));
        $this->refused(Refusal::OverCapacity, static fn (): mixed => $refusing->increase($inventory, 'rations', 1));
        $refusing->add($inventory, 'torch', 'Torch', 1, new Burden(0, 1));
        $this->refused(Refusal::OverCapacity, static fn (): mixed => $refusing->move($inventory, 'torch', Place::carriedBy(ResourceReference::of('character', '12'))));
        $refusing->move($inventory, 'torch', Place::carriedBy($this->bo));
        $refusing->increase($inventory, 'torch', 9);
        $this->refused(Refusal::OverCapacity, static fn (): mixed => $refusing->increase($inventory, 'torch', 1));
    }

    public function testTheCapacityComesFromTheCarrier(): void
    {
        $inventories = $this->service(capacity: new StrengthCapacity(['character:12' => 300, 'character:13' => -1]));
        $inventories->open($this->inventory, $this->party, 'Gear', 'srd-5.2');
        $inventories->add($this->inventory, 'rope', 'Rope', 9, new Burden(10), Place::carriedBy($this->ada));
        $inventories->add($this->inventory, 'tent', 'Tent', 1, new Burden(20), Place::carriedBy($this->bo));

        $load = $inventories->load($this->inventory, $this->ada);
        self::assertSame(300, $load?->capacity);
        self::assertSame(300, $load->limit);
        self::assertSame('unencumbered', $load->band?->key);

        $this->expectException(InvalidInventory::class);
        $this->expectExceptionMessageIsOrContains('A carrying capacity is 0 to 1000000000000; -1 is not.');
        $inventories->load($this->inventory, $this->bo);
    }

    public function testAnInventoryWithoutRulesIsNotWeighed(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'anvil', 'Anvil', 1, new Burden(100_000), Place::carriedBy($this->ada));

        self::assertNull($this->inventories->load($this->inventory, $this->ada));
        self::assertSame([], $this->inventories->loads($this->inventory));

        $this->inventories->useSystem($this->inventory, 'unknown-system');
        self::assertNull($this->inventories->load($this->inventory, $this->ada));
        self::assertSame([], $this->inventories->loads($this->inventory));

        $this->inventories->useSystem($this->inventory, 'srd-5.2');
        self::assertTrue($this->inventories->load($this->inventory, $this->ada)?->isOver());
    }

    public function testUnitsAreAddedAndTakenAway(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'arrows', 'Arrows', 20, new Burden(1), Place::carriedBy($this->ada));

        self::assertSame(25, $this->inventories->increase($this->inventory, 'arrows', 5, $this->gm, 'found')->quantity);
        self::assertSame(15, $this->inventories->decrease($this->inventory, 'arrows', 10, $this->ada, 'shot')?->quantity);
        self::assertSame(15, $this->inventories->item($this->inventory, 'arrows')->quantity);
        $this->refused(Refusal::NotEnough, fn (): mixed => $this->inventories->decrease($this->inventory, 'arrows', 16));
        self::assertNull($this->inventories->decrease($this->inventory, 'arrows', 15));
        self::assertSame([], $this->inventories->items($this->inventory));

        self::assertSame([
            'removed arrows 15 carrier:character:12 -',
            'decreased arrows 10 carrier:character:12 -',
            'increased arrows 5 - carrier:character:12',
            'added arrows 20 - carrier:character:12',
            'opened - - - -',
        ], $this->lines($this->inventories->log($this->inventory)));
        self::assertSame('shot', $this->inventories->log($this->inventory)[1]->reason);
        self::assertSame('character:12', $this->inventories->log($this->inventory)[1]->by);
        self::assertSame('found', $this->inventories->log($this->inventory)[2]->reason);
    }

    public function testAQuantityIsAtLeastOne(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'arrows', 'Arrows', 20);

        $this->invalid('A quantity is 1 to 1000000; 0 is not.', fn (): mixed => $this->inventories->increase($this->inventory, 'arrows', 0));
        $this->invalid('A quantity is 1 to 1000000; 0 is not.', fn (): mixed => $this->inventories->decrease($this->inventory, 'arrows', 0));
        $this->invalid('A quantity is 1 to 1000000; 1000001 is not.', fn (): mixed => $this->inventories->decrease($this->inventory, 'arrows', 1_000_001));
        $this->invalid('A quantity is 1 to 1000000; 0 is not.', fn (): mixed => $this->inventories->move($this->inventory, 'arrows', Place::stash(), 0, 'x'));
        $this->invalid('A quantity is 1 to 1000000; 0 is not.', fn (): mixed => $this->inventories->transfer($this->inventory, 'arrows', $this->party, null, 0));
        $this->invalid('An item quantity is 1 to 1000000; 1000001 is not.', fn (): mixed => $this->inventories->increase($this->inventory, 'arrows', 999_981));
        self::assertSame(1_000_000, $this->inventories->increase($this->inventory, 'arrows', 999_980)->quantity);
        $this->invalid('A reason is 1 to 200', fn (): mixed => $this->inventories->increase($this->inventory, 'arrows', 1, null, ' '));
        $this->invalid('A reason is 1 to 200', fn (): mixed => $this->inventories->decrease($this->inventory, 'arrows', 1, null, ' '));
        $this->invalid('A reason is 1 to 200', function (): void {
            $this->inventories->remove($this->inventory, 'arrows', null, ' ');
        });
        $this->invalid('A reason is 1 to 200', fn (): mixed => $this->inventories->move($this->inventory, 'arrows', Place::stash(), null, null, null, ' '));
        self::assertSame(1_000_000, $this->inventories->item($this->inventory, 'arrows')->quantity);
    }

    public function testAContainerIsSingleAndGoesOnlyOnceEmpty(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(), Place::stash(), new Container());
        $this->inventories->add($this->inventory, 'rope', 'Rope', 1, new Burden(), Place::inside('pack'));

        $this->refused(Refusal::SingleContainer, fn (): mixed => $this->inventories->increase($this->inventory, 'pack', 1));
        $this->refused(Refusal::ContainerNotEmpty, function (): void {
            $this->inventories->remove($this->inventory, 'pack');
        });
        $this->refused(Refusal::ContainerNotEmpty, fn (): mixed => $this->inventories->decrease($this->inventory, 'pack', 1));

        $this->inventories->remove($this->inventory, 'rope', $this->gm, 'burnt');
        $this->inventories->remove($this->inventory, 'pack');
        self::assertSame([], $this->inventories->items($this->inventory));
        self::assertSame('removed rope 1 container:pack -', $this->lines($this->inventories->log($this->inventory))[1]);
        self::assertSame('burnt', $this->inventories->log($this->inventory)[1]->reason);
    }

    public function testAnItemMovesWholeWithWhatItHolds(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(), Place::carriedBy($this->ada), new Container());
        $this->inventories->add($this->inventory, 'rope', 'Rope', 1, new Burden(), Place::inside('pack'));
        $this->inventories->equip($this->inventory, 'pack');

        $moved = $this->inventories->move($this->inventory, 'pack', Place::carriedBy($this->bo), by: $this->gm, reason: 'given');

        self::assertFalse($moved->equipped);
        self::assertSame('carrier:character:13', $moved->place->toString());
        self::assertSame('container:pack', $this->inventories->item($this->inventory, 'rope')->place->toString());
        self::assertSame(['pack'], $this->keys($this->store->carriedBy('character:13')));
        self::assertSame('moved pack 1 carrier:character:12 carrier:character:13', $this->lines($this->inventories->log($this->inventory))[0]);
        self::assertSame('given', $this->inventories->log($this->inventory)[0]->reason);
        self::assertSame('inventory:7 moved pack player:1', $this->recorder->summary()[\count($this->recorder->changes) - 1]);
    }

    public function testPartOfAStackMovesUnderANewKey(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'arrows', 'Arrows', 20, new Burden(1), Place::carriedBy($this->ada));
        $this->inventories->equip($this->inventory, 'arrows');
        $this->inventories->add($this->inventory, 'quiver', 'Quiver', 1, new Burden(), Place::stash(), new Container());

        $this->clock->modify('+1 minute');

        $moved = $this->inventories->move($this->inventory, 'arrows', Place::carriedBy($this->bo), 5, 'arrows.bo');

        self::assertSame('arrows.bo', $moved->key);
        self::assertSame(5, $moved->quantity);
        self::assertSame('2026-11-25 10:01:00', $moved->addedAt->format('Y-m-d H:i:s'));
        self::assertFalse($moved->equipped);
        $left = $this->inventories->item($this->inventory, 'arrows');
        self::assertSame(15, $left->quantity);
        self::assertTrue($left->equipped);
        self::assertSame('carrier:character:12', $left->place->toString());
        self::assertSame('moved arrows.bo 5 carrier:character:12 carrier:character:13', $this->lines($this->inventories->log($this->inventory))[0]);

        $this->invalid('Part of "arrows" moves under a new key; none is given.', fn (): mixed => $this->inventories->move($this->inventory, 'arrows', Place::stash(), 5));
        $this->invalid('A new key is given only when part of "arrows" moves.', fn (): mixed => $this->inventories->move($this->inventory, 'arrows', Place::stash(), 15, 'arrows.all'));
        $this->invalid('A new key is given only when part of "arrows" moves.', fn (): mixed => $this->inventories->move($this->inventory, 'arrows', Place::stash(), null, 'arrows.all'));
        $this->refused(Refusal::NotEnough, fn (): mixed => $this->inventories->move($this->inventory, 'arrows', Place::stash(), 16, 'arrows.more'));
        $this->refused(Refusal::ItemExists, fn (): mixed => $this->inventories->move($this->inventory, 'arrows', Place::stash(), 5, 'arrows.bo'));
        $this->refused(Refusal::NotAContainer, fn (): mixed => $this->inventories->move($this->inventory, 'arrows', Place::inside('arrows.bo'), 5, 'arrows.c'));

        $this->inventories->move($this->inventory, 'arrows', Place::inside('quiver'), 14, 'arrows.quiver');
        self::assertSame(1, $this->inventories->item($this->inventory, 'arrows')->quantity);
        self::assertSame('container:quiver', $this->inventories->item($this->inventory, 'arrows.quiver')->place->toString());
    }

    public function testAPartMovedIsCheckedAgainstTheContainer(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'arrows', 'Arrows', 20, new Burden(1));
        $this->inventories->add($this->inventory, 'quiver', 'Quiver', 1, new Burden(), Place::stash(), new Container(10));

        $this->refused(Refusal::ContainerFull, fn (): mixed => $this->inventories->move($this->inventory, 'arrows', Place::inside('quiver'), 11, 'arrows.q'));
        $this->refused(Refusal::ContainerFull, fn (): mixed => $this->inventories->move($this->inventory, 'arrows', Place::inside('quiver')));
        $this->inventories->move($this->inventory, 'arrows', Place::inside('quiver'), 10, 'arrows.q');
        self::assertSame(10, $this->inventories->item($this->inventory, 'arrows')->quantity);
    }

    public function testOnlyACarriedItemIsEquipped(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'sword', 'Sword', 1, new Burden(), Place::carriedBy($this->ada));
        $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(), Place::carriedBy($this->ada), new Container());
        $this->inventories->add($this->inventory, 'dagger', 'Dagger', 1, new Burden(), Place::inside('pack'));
        $this->inventories->add($this->inventory, 'axe', 'Axe');

        self::assertTrue($this->inventories->equip($this->inventory, 'sword', $this->ada)->equipped);
        self::assertTrue($this->inventories->item($this->inventory, 'sword')->equipped);
        self::assertTrue($this->inventories->equip($this->inventory, 'sword')->equipped);
        $this->refused(Refusal::NotCarried, fn (): mixed => $this->inventories->equip($this->inventory, 'dagger'));
        $this->refused(Refusal::NotCarried, fn (): mixed => $this->inventories->equip($this->inventory, 'axe'));
        self::assertFalse($this->inventories->unequip($this->inventory, 'sword', $this->ada)->equipped);
        self::assertFalse($this->inventories->item($this->inventory, 'sword')->equipped);
        self::assertFalse($this->inventories->unequip($this->inventory, 'axe')->equipped);

        self::assertSame(['unequipped sword - - -', 'equipped sword - - -'], \array_slice($this->lines($this->inventories->log($this->inventory)), 0, 2));
        self::assertSame(['inventory:7 equipped sword character:12', 'inventory:7 unequipped sword character:12'], \array_slice($this->recorder->summary(), -2));
        self::assertSame('character:12', $this->inventories->log($this->inventory)[0]->by);
    }

    public function testAnItemIsTransferredToAnotherInventory(): void
    {
        $target = ResourceReference::of('inventory', '8');
        $this->openWeighed();
        $this->inventories->open($target, $this->ada, "Ada's gear", 'srd-5.2');
        $this->inventories->add($this->inventory, 'gold-ring', 'Gold ring', 3, new Burden(1), Place::carriedBy($this->bo));
        $this->inventories->equip($this->inventory, 'gold-ring');

        $this->clock->modify('+1 minute');

        $arrived = $this->inventories->transfer($this->inventory, 'gold-ring', $target, Place::carriedBy($this->ada), 1, 'ring', $this->gm, 'share');

        self::assertSame('ring', $arrived->key);
        self::assertSame(1, $arrived->quantity);
        self::assertFalse($arrived->equipped);
        self::assertSame('inventory:8', $arrived->inventory->toString());
        self::assertSame('2026-11-25 10:01:00', $arrived->addedAt->format('Y-m-d H:i:s'));
        self::assertSame(2, $this->inventories->item($this->inventory, 'gold-ring')->quantity);
        self::assertTrue($this->inventories->item($this->inventory, 'gold-ring')->equipped);
        self::assertSame('transferred_out gold-ring 1 carrier:character:13 inventory:8', $this->lines($this->inventories->log($this->inventory))[0]);
        self::assertSame('transferred_in ring 1 inventory:7 carrier:character:12', $this->lines($this->inventories->log($target))[0]);
        self::assertSame('share', $this->inventories->log($target)[0]->reason);
        self::assertSame('player:1', $this->inventories->log($this->inventory)[0]->by);

        $whole = $this->inventories->transfer($this->inventory, 'gold-ring', $target);
        self::assertSame('gold-ring', $whole->key);
        self::assertSame(2, $whole->quantity);
        self::assertTrue($whole->place->isStash());
        self::assertSame([], $this->inventories->items($this->inventory));
        self::assertSame(['inventory:7 transferred_out gold-ring -', 'inventory:8 transferred_in gold-ring -'], \array_slice($this->recorder->summary(), -2));
    }

    public function testATransferIsChecked(): void
    {
        $target = ResourceReference::of('inventory', '8');
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->open($target, $this->ada, "Ada's gear", 'knave');
        $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(), Place::stash(), new Container());
        $this->inventories->add($this->inventory, 'rope', 'Rope', 2, new Burden(0, 1), Place::inside('pack'));
        $this->inventories->add($target, 'rope', 'Rope');
        $this->inventories->add($target, 'shield', 'Shield', 1, new Burden(0, 10), Place::carriedBy($this->ada));

        $this->invalid('A transfer goes to another inventory; move the item instead.', fn (): mixed => $this->inventories->transfer($this->inventory, 'rope', ResourceReference::of('inventory', '7')));
        $this->refused(Refusal::ContainerNotEmpty, fn (): mixed => $this->inventories->transfer($this->inventory, 'pack', $target));
        $this->refused(Refusal::NotEnough, fn (): mixed => $this->inventories->transfer($this->inventory, 'rope', $target, null, 3, 'rope.2'));
        $this->refused(Refusal::ItemExists, fn (): mixed => $this->inventories->transfer($this->inventory, 'rope', $target));
        $this->refused(Refusal::OverCapacity, fn (): mixed => $this->inventories->transfer($this->inventory, 'rope', $target, Place::carriedBy($this->ada), 1, 'rope.2'));
        $this->refused(Refusal::NotAContainer, fn (): mixed => $this->inventories->transfer($this->inventory, 'rope', $target, Place::inside('shield'), 1, 'rope.2'));
        $this->invalid('A reason is 1 to 200', fn (): mixed => $this->inventories->transfer($this->inventory, 'rope', $target, null, 1, 'rope.2', null, ''));

        $this->expectException(UnknownInventory::class);
        $this->inventories->transfer($this->inventory, 'rope', ResourceReference::of('inventory', '9'));
    }

    public function testAnEmptyContainerIsTransferred(): void
    {
        $target = ResourceReference::of('inventory', '8');
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->open($target, $this->ada, "Ada's gear");
        $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(), Place::stash(), new Container(5));

        $arrived = $this->inventories->transfer($this->inventory, 'pack', $target);

        self::assertSame(5, $arrived->container?->maxWeight);
        self::assertSame([], $this->inventories->items($this->inventory));
    }

    public function testTheLogKeepsItsNewestEntries(): void
    {
        $inventories = $this->service(new Limits(logPerInventory: 3));
        $inventories->open($this->inventory, $this->party, 'Gear');
        $inventories->add($this->inventory, 'a', 'A');
        $inventories->add($this->inventory, 'b', 'B');

        $this->clock->modify('+1 second');
        $inventories->add($this->inventory, 'c', 'C');

        self::assertSame(['added c 1 - stash', 'added b 1 - stash', 'added a 1 - stash'], $this->lines($inventories->log($this->inventory)));
        self::assertSame(['added c 1 - stash'], $this->lines($inventories->log($this->inventory, 1)));
        self::assertCount(3, $inventories->log($this->inventory, 1000));
        $this->invalid('The log entries read is 1 to 1000; 0 is not.', fn (): mixed => $inventories->log($this->inventory, 0));
        $this->invalid('The log entries read is 1 to 1000; 1001 is not.', fn (): mixed => $inventories->log($this->inventory, 1001));
    }

    public function testTheLogReadsFiftyEntriesUnlessToldOtherwise(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');

        for ($i = 1; $i <= 59; ++$i) {
            $this->inventories->add($this->inventory, 'item-'.$i, 'Item '.$i);
        }

        self::assertCount(50, $this->inventories->log($this->inventory));
        self::assertCount(51, $this->inventories->log($this->inventory, 51));
        self::assertCount(49, $this->inventories->log($this->inventory, 49));
    }

    public function testTheLogOfAnUnknownInventoryIsRefused(): void
    {
        $this->expectException(UnknownInventory::class);
        $this->expectExceptionMessageIsOrContains('No inventory is known as "inventory:7".');

        $this->inventories->log($this->inventory);
    }

    public function testAnItemOfAnUnknownInventoryIsNamedByItsInventory(): void
    {
        $this->expectException(UnknownInventory::class);
        $this->expectExceptionMessageIsOrContains('No inventory is known as "inventory:7".');

        $this->inventories->item($this->inventory, 'rope');
    }

    public function testAnInvalidReasonChangesNothing(): void
    {
        $target = ResourceReference::of('inventory', '8');
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->open($target, $this->ada, 'Other');
        $this->inventories->add($this->inventory, 'rope', 'Rope');

        $this->invalid('A reason is 1 to 200', fn (): mixed => $this->inventories->add($this->inventory, 'torch', 'Torch', reason: ' '));
        self::assertNotInstanceOf(Item::class, $this->store->item('inventory:7', 'torch'));

        $this->invalid('A reason is 1 to 200', function (): void {
            $this->inventories->remove($this->inventory, 'rope', null, ' ');
        });
        self::assertInstanceOf(Item::class, $this->store->item('inventory:7', 'rope'));

        $this->invalid('A reason is 1 to 200', fn (): mixed => $this->inventories->transfer($this->inventory, 'rope', $target, null, null, null, null, ' '));
        self::assertNotInstanceOf(Item::class, $this->store->item('inventory:8', 'rope'));
        self::assertInstanceOf(Item::class, $this->store->item('inventory:7', 'rope'));

        $this->invalid('A reason is 1 to 200', fn (): mixed => $this->inventories->move($this->inventory, 'rope', Place::carriedBy($this->ada), null, null, null, ' '));
        self::assertTrue($this->store->item('inventory:7', 'rope')?->place->isStash());
    }

    public function testATransferredItemIsSavedInTheTargetInventory(): void
    {
        $target = ResourceReference::of('inventory', '8');
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->open($target, $this->ada, 'Other');
        $this->inventories->add($this->inventory, 'rope', 'Rope', 3);

        $this->inventories->transfer($this->inventory, 'rope', $target, null, 1, 'rope.part');

        self::assertSame(['rope.part'], $this->keys($this->store->items('inventory:8')));
        self::assertSame(1, $this->store->item('inventory:8', 'rope.part')?->quantity);
    }

    public function testContainersNestDeeperThanTheirOwnHeightAllows(): void
    {
        $inventories = $this->service(new Limits(containerDepth: 2));
        $inventories->open($this->inventory, $this->party, 'Gear');
        $inventories->add($this->inventory, 'chest', 'Chest', 1, new Burden(), Place::stash(), new Container());
        $inventories->add($this->inventory, 'box', 'Box', 1, new Burden(), Place::inside('chest'), new Container());
        $inventories->add($this->inventory, 'ring', 'Ring', 1, new Burden(), Place::inside('box'));
        $inventories->add($this->inventory, 'sack', 'Sack', 1, new Burden(), Place::stash(), new Container());

        $this->refused(Refusal::TooDeep, fn (): mixed => $inventories->move($this->inventory, 'chest', Place::inside('sack')));
    }

    public function testTheFillOfAPlainItemHasNoBounds(): void
    {
        $fill = new Packing([new Item($this->inventory, 'rope', 'Rope', 1, new Burden(2), Place::stash(), $this->clock->now())])->fill(new Item($this->inventory, 'rope', 'Rope', 1, new Burden(2), Place::stash(), $this->clock->now()));

        self::assertNull($fill->maxWeight);
        self::assertNull($fill->maxSlots);
    }

    public function testTheLoadsAreReadInTheOrderOfTheReferencesWhateverTheOrderAdded(): void
    {
        $this->openWeighed();
        $this->inventories->add($this->inventory, 'sword', 'Sword', 1, new Burden(3), Place::carriedBy($this->bo));
        $this->clock->modify('+1 second');
        $this->inventories->add($this->inventory, 'bow', 'Bow', 1, new Burden(2), Place::carriedBy($this->ada));

        self::assertSame(['character:12', 'character:13'], array_map(static fn (Load $load): string => $load->carrier->toString(), $this->inventories->loads($this->inventory)));
    }

    public function testTheLogIsReadNewestFirstWhateverTheOrderWritten(): void
    {
        $store = new InMemoryInventoryStore();
        $late = new LogEntry('inventory:7', Change::Added, 'late', 1, null, 'stash', null, null, new DateTimeImmutable('2026-11-25 11:00:00'));
        $early = new LogEntry('inventory:7', Change::Added, 'early', 1, null, 'stash', null, null, new DateTimeImmutable('2026-11-25 09:00:00'));
        $store->appendLog($late, 10);
        $store->appendLog($early, 10);

        self::assertSame(['late', 'early'], array_map(static fn (LogEntry $entry): ?string => $entry->item, $store->log('inventory:7', 10)));
        self::assertSame([], $store->log('inventory:8', 10));
    }

    public function testItemsOfTheSameMomentAreReadByKey(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->add($this->inventory, 'b', 'B');
        $this->inventories->add($this->inventory, 'a', 'A');

        $this->clock->modify('-1 minute');
        $this->inventories->add($this->inventory, 'c', 'C');

        self::assertSame(['c', 'a', 'b'], $this->keys($this->inventories->items($this->inventory)));
        self::assertSame(3, $this->store->countItems('inventory:7'));
        self::assertSame(0, $this->store->countItems('inventory:8'));
        self::assertNull($this->store->item('inventory:8', 'a'));
    }

    public function testAForgottenReferenceLeavesNoTrace(): void
    {
        $other = ResourceReference::of('inventory', '6');
        $this->inventories->open($this->inventory, $this->party, 'Gear');
        $this->inventories->open($other, $this->ada, "Ada's gear");
        $this->inventories->add($this->inventory, 'pack', 'Backpack', 1, new Burden(), Place::carriedBy($this->ada), new Container(), $this->ada);
        $this->inventories->add($this->inventory, 'rope', 'Rope', 1, new Burden(), Place::inside('pack'));
        $this->inventories->add($this->inventory, 'sword', 'Sword', 1, new Burden(), Place::carriedBy($this->bo), by: $this->bo);
        $this->inventories->add($other, 'ring', 'Ring', 2, new Burden(), Place::carriedBy($this->ada));
        $this->inventories->equip($other, 'ring');
        self::assertSame(['ring', 'pack'], $this->keys($this->store->carriedBy('character:12')));

        $this->inventories->forget($this->ada);

        self::assertSame([], $this->store->carriedBy('character:12'));
        self::assertTrue($this->inventories->item($this->inventory, 'pack')->place->isStash());
        self::assertSame('container:pack', $this->inventories->item($this->inventory, 'rope')->place->toString());
        self::assertFalse($this->inventories->item($other, 'ring')->equipped);
        self::assertSame('carrier:character:13', $this->inventories->item($this->inventory, 'sword')->place->toString());
        self::assertSame(['forgotten ring 2 carrier stash', 'equipped ring - - -', 'added ring 2 - carrier', 'opened - - - -'], $this->lines($this->inventories->log($other)));

        $log = $this->inventories->log($this->inventory);
        self::assertSame('forgotten pack 1 carrier stash', $this->lines($log)[0]);
        self::assertSame('added sword 1 - carrier:character:13', $this->lines($log)[1]);
        self::assertSame('character:13', $log[1]->by);
        self::assertSame('added pack 1 - carrier', $this->lines($log)[3]);
        self::assertNull($log[3]->by);
        self::assertSame(['inventory:6 forgotten ring -', 'inventory:7 forgotten pack -'], \array_slice($this->recorder->summary(), -2));
        self::assertSame(['Gear'], array_map(static fn (Inventory $inventory): string => $inventory->name, $this->inventories->inventoriesOf($this->party)));
    }

    public function testAnUnknownItemIsNamed(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear');

        $this->expectException(UnknownInventory::class);
        $this->expectExceptionMessageIsOrContains('The inventory "inventory:7" holds no item under the key "rope".');
        $this->inventories->item($this->inventory, 'rope');
    }

    public function testObserversAreTakenFromAnyIterable(): void
    {
        $recorder = new Recorder();
        $inventories = new Inventories($this->store, $this->clock, observers: (static function () use ($recorder): Generator {
            yield $recorder;
        })());
        $inventories->open($this->inventory, $this->party, 'Gear', null, $this->gm);

        self::assertCount(1, $recorder->changes);
        self::assertSame(Change::Opened, $recorder->changes[0]->change);
        self::assertSame('2026-11-25 10:00:00', $recorder->changes[0]->at->format('Y-m-d H:i:s'));
        self::assertSame('player:1', $recorder->changes[0]->by);
        self::assertNull($recorder->changes[0]->item);
    }

    private function service(?Limits $limits = null, ?StaticRuleBook $rules = null, ?StrengthCapacity $capacity = null): Inventories
    {
        $rules ??= new StaticRuleBook([
            'srd-5.2' => new CarryRules(Measure::Weight, 150, [
                new LoadBand('unencumbered', 1, 3),
                new LoadBand('encumbered', 2, 3),
                new LoadBand('heavily-encumbered', 1),
            ]),
            'knave' => new CarryRules(Measure::Slots, 10, [new LoadBand('fine', 1)], true),
        ]);

        return new Inventories($this->store, $this->clock, $rules, $capacity ?? new StrengthCapacity([]), $limits ?? new Limits(), [$this->recorder]);
    }

    private function openWeighed(): void
    {
        $this->inventories->open($this->inventory, $this->party, 'Gear', 'srd-5.2');
    }

    /**
     * @param list<Item> $items
     *
     * @return list<string>
     */
    private function keys(array $items): array
    {
        return array_map(static fn (Item $item): string => $item->key, $items);
    }

    /**
     * @param list<LogEntry> $entries
     *
     * @return list<string>
     */
    private function lines(array $entries): array
    {
        return array_map(static fn (LogEntry $entry): string => \sprintf('%s %s %s %s %s', $entry->change->value, $entry->item ?? '-', $entry->quantity ?? '-', $entry->from ?? '-', $entry->to ?? '-'), $entries);
    }

    /**
     * @param Closure(): mixed $act
     */
    private function refused(Refusal $refusal, Closure $act): void
    {
        try {
            $act();
            self::fail(\sprintf('The act is refused: %s.', $refusal->value));
        } catch (InventoryRefused $inventoryRefused) {
            self::assertSame($refusal, $inventoryRefused->refusal);
        }
    }

    /**
     * @param non-empty-string $message
     * @param Closure(): mixed $act
     */
    private function invalid(string $message, Closure $act): void
    {
        try {
            $act();
            self::fail(\sprintf('The value is refused: %s', $message));
        } catch (InvalidInventory $invalidInventory) {
            self::assertStringStartsWith($message, $invalidInventory->getMessage());
        }
    }
}
