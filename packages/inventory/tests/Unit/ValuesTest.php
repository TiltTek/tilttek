<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Inventory\Burden;
use Tilttek\Inventory\CarryRules;
use Tilttek\Inventory\Change;
use Tilttek\Inventory\Container;
use Tilttek\Inventory\Exception\InvalidInventory;
use Tilttek\Inventory\Exception\InventoryException;
use Tilttek\Inventory\Exception\InventoryRefused;
use Tilttek\Inventory\Exception\UnknownInventory;
use Tilttek\Inventory\Fill;
use Tilttek\Inventory\Inventory;
use Tilttek\Inventory\Item;
use Tilttek\Inventory\Limits;
use Tilttek\Inventory\Load;
use Tilttek\Inventory\LoadBand;
use Tilttek\Inventory\LogEntry;
use Tilttek\Inventory\Measure;
use Tilttek\Inventory\Place;
use Tilttek\Inventory\Refusal;
use Tilttek\Inventory\Rules\DefaultCapacity;
use Tilttek\Inventory\Rules\StaticRuleBook;
use Tilttek\Inventory\Text;

#[CoversClass(Burden::class)]
#[CoversClass(Container::class)]
#[CoversClass(Place::class)]
#[CoversClass(Inventory::class)]
#[CoversClass(Item::class)]
#[CoversClass(LoadBand::class)]
#[CoversClass(CarryRules::class)]
#[CoversClass(Load::class)]
#[CoversClass(Fill::class)]
#[CoversClass(Limits::class)]
#[CoversClass(LogEntry::class)]
#[CoversClass(Text::class)]
#[CoversClass(StaticRuleBook::class)]
#[CoversClass(DefaultCapacity::class)]
#[CoversClass(InvalidInventory::class)]
#[CoversClass(InventoryRefused::class)]
#[CoversClass(UnknownInventory::class)]
final class ValuesTest extends TestCase
{
    private ResourceReference $inventory;

    private ResourceReference $ada;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->inventory = ResourceReference::of('inventory', '7');
        $this->ada = ResourceReference::of('character', '12');
        $this->now = new DateTimeImmutable('2026-11-25 10:00:00');
    }

    public function testABurdenWeighsEachUnitAndFillsSlotsByStack(): void
    {
        $arrows = new Burden(1, 1, 20);

        self::assertSame(21, $arrows->of(21, Measure::Weight));
        self::assertSame(1, $arrows->of(1, Measure::Slots));
        self::assertSame(1, $arrows->of(20, Measure::Slots));
        self::assertSame(2, $arrows->of(21, Measure::Slots));
        self::assertSame(6, new Burden(10, 2)->of(3, Measure::Slots));
        self::assertSame(30, new Burden(10, 2)->of(3, Measure::Weight));
        self::assertSame(0, Burden::none()->of(5, Measure::Weight));
        self::assertSame(0, Burden::none()->of(5, Measure::Slots));
        self::assertSame(1, Burden::none()->perSlot);
    }

    public function testABurdenAcceptsItsBounds(): void
    {
        $heaviest = new Burden(Burden::MAX_WEIGHT, Burden::MAX_SLOTS, Burden::MAX_PER_SLOT);

        self::assertSame(100_000, $heaviest->weight);
        self::assertSame(1000, $heaviest->slots);
        self::assertSame(10_000, $heaviest->perSlot);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidValues(): iterable
    {
        $inventory = ResourceReference::of('inventory', '7');
        $owner = ResourceReference::of('character', '12');
        $now = new DateTimeImmutable('2026-11-25 10:00:00');

        yield 'negative weight' => [static fn (): Burden => new Burden(-1), 'The weight of one unit is 0 to 100000; -1 is not.'];
        yield 'too heavy' => [static fn (): Burden => new Burden(100_001), 'The weight of one unit is 0 to 100000; 100001 is not.'];
        yield 'negative slots' => [static fn (): Burden => new Burden(0, -1), 'The slots of a stack is 0 to 1000; -1 is not.'];
        yield 'too many slots' => [static fn (): Burden => new Burden(0, 1001), 'The slots of a stack is 0 to 1000; 1001 is not.'];
        yield 'empty stack' => [static fn (): Burden => new Burden(0, 1, 0), 'The units of a stack is 1 to 10000; 0 is not.'];
        yield 'huge stack' => [static fn (): Burden => new Burden(0, 1, 10_001), 'The units of a stack is 1 to 10000; 10001 is not.'];
        yield 'container of no weight' => [static fn (): Container => new Container(0), 'The weight a container holds is 1 to 1000000000; 0 is not.'];
        yield 'container too big' => [static fn (): Container => new Container(1_000_000_001), 'The weight a container holds is 1 to 1000000000; 1000000001 is not.'];
        yield 'container of no slot' => [static fn (): Container => new Container(null, 0), 'The slots a container holds is 1 to 1000000000; 0 is not.'];
        yield 'container of too many slots' => [static fn (): Container => new Container(null, 1_000_000_001), 'The slots a container holds is 1 to 1000000000; 1000000001 is not.'];
        yield 'bad container key' => [static fn (): Place => Place::inside('Back Pack'), 'The key of a container must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "Back Pack" is not.'];
        yield 'bad place' => [static fn (): Place => Place::fromString('cart'), 'A place is "stash", "carrier:" then a reference, or "container:" then a key; "cart" is not.'];
        yield 'empty inventory name' => [static fn (): Inventory => new Inventory($inventory, $owner, '', null, $now), 'An inventory name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.'];
        yield 'long inventory name' => [static fn (): Inventory => new Inventory($inventory, $owner, str_repeat('é', 101), null, $now), 'An inventory name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.'];
        yield 'padded inventory name' => [static fn (): Inventory => new Inventory($inventory, $owner, ' Gear', null, $now), 'An inventory name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.'];
        yield 'inventory name with a tab' => [static fn (): Inventory => new Inventory($inventory, $owner, "Ge\tar", null, $now), 'An inventory name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.'];
        yield 'bad system' => [static fn (): Inventory => new Inventory($inventory, $owner, 'Gear', 'SRD 5', $now), 'The key of a game system must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "SRD 5" is not.'];
        yield 'bad item key' => [static fn (): Item => new Item($inventory, 'rope.', 'Rope', 1, new Burden(), Place::stash(), $now), 'The key of an item must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "rope." is not.'];
        yield 'long item key' => [static fn (): Item => new Item($inventory, str_repeat('a', 97), 'Rope', 1, new Burden(), Place::stash(), $now), 'The key of an item must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "'.str_repeat('a', 97).'" is not.'];
        yield 'item key with a line break' => [static fn (): Item => new Item($inventory, "rope\n", 'Rope', 1, new Burden(), Place::stash(), $now), "The key of an item must be 1 to 96 lowercase letters, digits, \"_\", \"-\" or single dots between them; \"rope\n\" is not."];
        yield 'bad item name' => [static fn (): Item => new Item($inventory, 'rope', 'Rope ', 1, new Burden(), Place::stash(), $now), 'An item name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.'];
        yield 'no unit' => [static fn (): Item => new Item($inventory, 'rope', 'Rope', 0, new Burden(), Place::stash(), $now), 'An item quantity is 1 to 1000000; 0 is not.'];
        yield 'too many units' => [static fn (): Item => new Item($inventory, 'rope', 'Rope', 1_000_001, new Burden(), Place::stash(), $now), 'An item quantity is 1 to 1000000; 1000001 is not.'];
        yield 'two containers' => [static fn (): Item => new Item($inventory, 'sacks', 'Sacks', 2, new Burden(), Place::stash(), $now, new Container()), 'A container is a single item; the quantity of "sacks" is 2.'];
        yield 'equipped in the stash' => [static fn (): Item => new Item($inventory, 'sword', 'Sword', 1, new Burden(), Place::stash(), $now, null, true), 'Only an item on a carrier is equipped; "sword" is not on one.'];
        yield 'equipped in a container' => [static fn (): Item => new Item($inventory, 'sword', 'Sword', 1, new Burden(), Place::inside('pack'), $now, null, true), 'Only an item on a carrier is equipped; "sword" is not on one.'];
        yield 'bad band key' => [static fn (): LoadBand => new LoadBand('Light', 1), 'The key of a load band must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "Light" is not.'];
        yield 'band of nothing' => [static fn (): LoadBand => new LoadBand('light', 0), 'The numerator of a load band is 1 to 1000; 0 is not.'];
        yield 'band too high' => [static fn (): LoadBand => new LoadBand('light', 1001), 'The numerator of a load band is 1 to 1000; 1001 is not.'];
        yield 'band over zero' => [static fn (): LoadBand => new LoadBand('light', 1, 0), 'The denominator of a load band is 1 to 1000; 0 is not.'];
        yield 'band over too much' => [static fn (): LoadBand => new LoadBand('light', 1, 1001), 'The denominator of a load band is 1 to 1000; 1001 is not.'];
        yield 'no band' => [static fn (): CarryRules => new CarryRules(Measure::Weight, 10, []), 'Carrying rules have 1 to 10 load bands; these have 0.'];
        yield 'eleven bands' => [static fn (): CarryRules => new CarryRules(Measure::Weight, 10, array_map(static fn (int $i): LoadBand => new LoadBand('b'.$i, $i), range(1, 11))), 'Carrying rules have 1 to 10 load bands; these have 11.'];
        yield 'band twice' => [static fn (): CarryRules => new CarryRules(Measure::Weight, 10, [new LoadBand('light', 1, 2), new LoadBand('light', 1)]), 'The load band "light" is declared twice.'];
        yield 'bands not rising' => [static fn (): CarryRules => new CarryRules(Measure::Weight, 10, [new LoadBand('light', 2, 4), new LoadBand('heavy', 1, 2)]), 'The load band "heavy" must end above "light".'];
        yield 'negative capacity' => [static fn (): CarryRules => new CarryRules(Measure::Weight, -1, [new LoadBand('light', 1)]), 'A carrying capacity is 0 to 1000000000000; -1 is not.'];
        yield 'huge capacity' => [static fn (): int => CarryRules::capacity(1_000_000_000_001), 'A carrying capacity is 0 to 1000000000000; 1000000000001 is not.'];
        yield 'no inventory per owner' => [static fn (): Limits => new Limits(inventoriesPerOwner: 0), 'The limit of inventories per owner is 1 to 1000; 0 is not.'];
        yield 'too many inventories per owner' => [static fn (): Limits => new Limits(inventoriesPerOwner: 1001), 'The limit of inventories per owner is 1 to 1000; 1001 is not.'];
        yield 'no item' => [static fn (): Limits => new Limits(itemsPerInventory: 0), 'The limit of items per inventory is 1 to 10000; 0 is not.'];
        yield 'too many items' => [static fn (): Limits => new Limits(itemsPerInventory: 10_001), 'The limit of items per inventory is 1 to 10000; 10001 is not.'];
        yield 'no depth' => [static fn (): Limits => new Limits(containerDepth: 0), 'The limit of container depth is 1 to 8; 0 is not.'];
        yield 'too deep' => [static fn (): Limits => new Limits(containerDepth: 9), 'The limit of container depth is 1 to 8; 9 is not.'];
        yield 'no log' => [static fn (): Limits => new Limits(logPerInventory: 0), 'The limit of log entries per inventory is 1 to 100000; 0 is not.'];
        yield 'long log' => [static fn (): Limits => new Limits(logPerInventory: 100_001), 'The limit of log entries per inventory is 1 to 100000; 100001 is not.'];
        yield 'long reason' => [static fn (): LogEntry => new LogEntry('inventory:7', Change::Added, 'rope', 1, null, 'stash', null, str_repeat('a', 201), $now), 'A reason is 1 to 200 characters of UTF-8 without control characters nor surrounding spaces.'];
        yield 'bad rule book key' => [static fn (): StaticRuleBook => new StaticRuleBook(['SRD' => new CarryRules(Measure::Weight, 1, [new LoadBand('light', 1)])]), 'The key of a game system must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "SRD" is not.'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('invalidValues')]
    public function testAValueOutOfBoundsIsRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidInventory::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTheErrorMessagesAreExact(): void
    {
        try {
            new Burden(-1);
            self::fail('A negative weight is refused.');
        } catch (InvalidInventory $invalidInventory) {
            self::assertSame('The weight of one unit is 0 to 100000; -1 is not.', $invalidInventory->getMessage());
        }
    }

    public function testAContainerHoldsWithinItsBounds(): void
    {
        $sack = new Container(30, 10);

        self::assertTrue($sack->holds(30, 10));
        self::assertFalse($sack->holds(31, 10));
        self::assertFalse($sack->holds(30, 11));
        self::assertTrue($sack->countsContents);
        self::assertTrue(new Container()->holds(\PHP_INT_MAX, \PHP_INT_MAX));
        self::assertTrue(new Container(null, 1)->holds(\PHP_INT_MAX, 1));
        self::assertTrue(new Container(1)->holds(1, \PHP_INT_MAX));
        self::assertFalse(new Container(1, null, false)->countsContents);

        $biggest = new Container(Container::MAX_CAPACITY, Container::MAX_CAPACITY);
        self::assertSame(1_000_000_000, $biggest->maxWeight);
        self::assertSame(1_000_000_000, $biggest->maxSlots);
        self::assertSame(1, new Container(1, 1)->maxSlots);
    }

    public function testAPlaceIsWrittenAndReadBack(): void
    {
        $stash = Place::stash();
        $carried = Place::carriedBy($this->ada);
        $inside = Place::inside('backpack');

        self::assertSame('stash', $stash->toString());
        self::assertSame('carrier:character:12', $carried->toString());
        self::assertSame('container:backpack', $inside->toString());
        self::assertTrue($stash->isStash());
        self::assertFalse($carried->isStash());
        self::assertFalse($inside->isStash());
        self::assertTrue(Place::fromString('stash')->equals($stash));
        self::assertTrue(Place::fromString('carrier:character:12')->equals($carried));
        self::assertSame('character:12', Place::fromString('carrier:character:12')->carrier?->toString());
        self::assertSame('backpack', Place::fromString('container:backpack')->container);
        self::assertNull(Place::fromString('container:backpack')->carrier);
        self::assertFalse($carried->equals($inside));
        self::assertFalse($stash->equals(Place::carriedBy(ResourceReference::of('character', '13'))));
    }

    public function testAnInventoryIsRenamedAndChangesSystem(): void
    {
        $inventory = new Inventory($this->inventory, $this->ada, str_repeat('é', 100), 'srd-5.2', $this->now);

        $renamed = $inventory->renamed('Gear');
        self::assertSame('Gear', $renamed->name);
        self::assertSame('srd-5.2', $renamed->system);
        self::assertSame($this->ada, $renamed->owner);
        self::assertSame($this->inventory, $renamed->reference);
        self::assertSame($this->now, $renamed->openedAt);

        $changed = $inventory->withSystem(null);
        self::assertNull($changed->system);
        self::assertSame(str_repeat('é', 100), $changed->name);
        self::assertSame($this->now, $changed->openedAt);
        self::assertSame($this->ada, $changed->owner);
    }

    public function testAnItemCarriesItsChanges(): void
    {
        $place = Place::carriedBy($this->ada);
        $item = new Item($this->inventory, 'sword', str_repeat('a', 100), 1_000_000, new Burden(3), $place, $this->now, null, true);

        self::assertFalse($item->isContainer());
        self::assertSame(3_000_000, $item->own(Measure::Weight));

        $fewer = $item->withQuantity(2);
        self::assertSame(2, $fewer->quantity);
        self::assertTrue($fewer->equipped);
        self::assertSame($place, $fewer->place);
        self::assertSame($this->now, $fewer->addedAt);

        $stashed = $item->placed(Place::stash());
        self::assertFalse($stashed->equipped);
        self::assertTrue($stashed->place->isStash());
        self::assertSame(1_000_000, $stashed->quantity);
        self::assertSame($item->burden, $stashed->burden);
        self::assertSame($this->now, $stashed->addedAt);

        $unequipped = $item->withEquipped(false);
        self::assertFalse($unequipped->equipped);
        self::assertSame($place, $unequipped->place);

        $later = new DateTimeImmutable('2026-11-26 10:00:00');
        $copy = $item->copiedAs(ResourceReference::of('inventory', '8'), 'sword.copy', 4, Place::stash(), $later);
        self::assertSame('inventory:8', $copy->inventory->toString());
        self::assertSame('sword.copy', $copy->key);
        self::assertSame(4, $copy->quantity);
        self::assertFalse($copy->equipped);
        self::assertSame($later, $copy->addedAt);
        self::assertSame($item->name, $copy->name);
        self::assertSame($item->burden, $copy->burden);
    }

    public function testAContainerItemKeepsItsContainer(): void
    {
        $sack = new Container(30);
        $item = new Item($this->inventory, 'sack', 'Sack', 1, new Burden(1), Place::carriedBy($this->ada), $this->now, $sack, true);

        self::assertTrue($item->isContainer());
        self::assertSame($sack, $item->placed(Place::stash())->container);
        self::assertSame($sack, $item->withEquipped(false)->container);
        self::assertSame($sack, $item->withQuantity(1)->container);
        self::assertSame($sack, $item->copiedAs($this->inventory, 'sack.2', 1, Place::stash(), $this->now)->container);
    }

    public function testALoadBandAdmitsLoadsByFraction(): void
    {
        $third = new LoadBand('unencumbered', 1, 3);

        self::assertTrue($third->admits(50, 150));
        self::assertFalse($third->admits(51, 150));
        self::assertSame(50, $third->limitFor(150));
        self::assertSame(33, $third->limitFor(100));
        self::assertSame(100, new LoadBand('encumbered', 2, 3)->limitFor(150));
        self::assertSame(1, new LoadBand('all', 1)->denominator);
        self::assertTrue($third->endsBefore(new LoadBand('half', 1, 2)));
        self::assertFalse($third->endsBefore(new LoadBand('third', 2, 6)));
        self::assertFalse(new LoadBand('half', 1, 2)->endsBefore($third));

        $edge = new LoadBand('edge', LoadBand::MAX_TERM, LoadBand::MAX_TERM);
        self::assertSame(1000, $edge->numerator);
        self::assertSame(1000, $edge->denominator);
    }

    public function testCarryRulesFindTheLightestBand(): void
    {
        $rules = new CarryRules(Measure::Weight, 150, [
            new LoadBand('unencumbered', 1, 3),
            new LoadBand('encumbered', 2, 3),
            new LoadBand('heavily-encumbered', 1),
        ]);

        self::assertSame('unencumbered', $rules->bandFor(0, 150)?->key);
        self::assertSame('unencumbered', $rules->bandFor(50, 150)?->key);
        self::assertSame('encumbered', $rules->bandFor(51, 150)?->key);
        self::assertSame('heavily-encumbered', $rules->bandFor(150, 150)?->key);
        self::assertNull($rules->bandFor(151, 150));
        self::assertSame('heavily-encumbered', $rules->lastBand()->key);
        self::assertSame(150, $rules->defaultCapacity);
        self::assertFalse($rules->refuseOverCapacity);
        self::assertSame(Measure::Weight, $rules->measure);
        self::assertCount(3, $rules->bands);
        self::assertSame(0, CarryRules::capacity(0));
        self::assertSame(1_000_000_000_000, CarryRules::capacity(CarryRules::MAX_CAPACITY));
    }

    public function testCarryRulesAcceptTenBandsAndKeepThemInOrder(): void
    {
        $bands = array_map(static fn (int $i): LoadBand => new LoadBand('b'.$i, $i), range(1, 10));
        $rules = new CarryRules(Measure::Slots, 10, $bands, true);

        self::assertCount(10, $rules->bands);
        self::assertSame('b1', $rules->bands[0]->key);
        self::assertSame('b10', $rules->lastBand()->key);
        self::assertTrue($rules->refuseOverCapacity);
        self::assertSame(1_000_000_000_000, new CarryRules(Measure::Slots, 1_000_000_000_000, [new LoadBand('b', 1)])->defaultCapacity);
    }

    public function testALoadTellsItsBandAndRoom(): void
    {
        $band = new LoadBand('light', 1);
        $fits = new Load($this->ada, Measure::Weight, 40, 100, $band, 100);
        $over = new Load($this->ada, Measure::Weight, 140, 100, null, 100);

        self::assertFalse($fits->isOver());
        self::assertSame(60, $fits->room());
        self::assertTrue($over->isOver());
        self::assertSame(0, $over->room());
        self::assertSame(0, new Load($this->ada, Measure::Weight, 100, 100, $band, 100)->room());
    }

    public function testAFillTellsWhatIsLeft(): void
    {
        $fill = new Fill('pack', 20, 3, 30, 3);

        self::assertSame(10, $fill->weightLeft());
        self::assertSame(0, $fill->slotsLeft());
        self::assertSame(0, new Fill('pack', 40, 4, 30, 3)->weightLeft());
        self::assertSame(0, new Fill('pack', 40, 4, 30, 3)->slotsLeft());
        self::assertNull(new Fill('bag', 40, 4, null, null)->weightLeft());
        self::assertNull(new Fill('bag', 40, 4, null, null)->slotsLeft());
    }

    public function testLimitsHaveDefaultsAndAcceptTheirBounds(): void
    {
        $defaults = new Limits();

        self::assertSame(20, $defaults->inventoriesPerOwner);
        self::assertSame(1000, $defaults->itemsPerInventory);
        self::assertSame(4, $defaults->containerDepth);
        self::assertSame(1000, $defaults->logPerInventory);

        $lowest = new Limits(1, 1, 1, 1);
        self::assertSame(1, $lowest->inventoriesPerOwner);

        $highest = new Limits(1000, 10_000, 8, 100_000);
        self::assertSame(1000, $highest->inventoriesPerOwner);
        self::assertSame(10_000, $highest->itemsPerInventory);
        self::assertSame(8, $highest->containerDepth);
        self::assertSame(100_000, $highest->logPerInventory);
    }

    public function testALogEntryForgetsAReference(): void
    {
        $entry = new LogEntry('inventory:7', Change::Moved, 'rope', 2, 'carrier:character:12', 'carrier:character:12', 'character:12', str_repeat('a', 200), $this->now);
        $other = new LogEntry('inventory:7', Change::Moved, 'rope', 2, 'stash', 'carrier:character:13', 'player:1', 'sold', $this->now);

        self::assertTrue($entry->names('character:12'));
        self::assertFalse($other->names('character:12'));
        self::assertTrue($other->names('player:1'));
        self::assertTrue($other->names('character:13'));
        self::assertTrue(new LogEntry('inventory:7', Change::Moved, 'rope', 2, 'carrier:character:13', 'stash', null, null, $this->now)->names('character:13'));

        $erased = $entry->without('character:12');
        self::assertSame('carrier', $erased->from);
        self::assertSame('carrier', $erased->to);
        self::assertNull($erased->by);
        self::assertSame(str_repeat('a', 200), $erased->reason);
        self::assertSame('rope', $erased->item);
        self::assertSame(2, $erased->quantity);
        self::assertSame(Change::Moved, $erased->change);
        self::assertSame('inventory:7', $erased->inventory);
        self::assertSame($this->now, $erased->at);

        $kept = $other->without('character:12');
        self::assertSame('stash', $kept->from);
        self::assertSame('carrier:character:13', $kept->to);
        self::assertSame('player:1', $kept->by);
    }

    public function testTheRuleBookAndTheDefaultCapacityGiveTheRules(): void
    {
        $rules = new CarryRules(Measure::Weight, 150, [new LoadBand('light', 1)]);
        $book = new StaticRuleBook(['srd-5.2' => $rules]);
        $inventory = new Inventory($this->inventory, $this->ada, 'Gear', 'srd-5.2', $this->now);

        self::assertSame($rules, $book->rules('srd-5.2'));
        self::assertNull($book->rules('other'));
        self::assertNull(new StaticRuleBook()->rules('srd-5.2'));
        self::assertSame(150, new DefaultCapacity()->capacity($inventory, $this->ada, $rules));
    }

    public function testEachRefusalHasItsMessage(): void
    {
        $messages = [];

        foreach (Refusal::cases() as $refusal) {
            $exception = InventoryRefused::because($refusal);
            self::assertSame($refusal, $exception->refusal);
            self::assertInstanceOf(InventoryException::class, $exception);
            $messages[$exception->getMessage()] = true;
        }

        self::assertCount(\count(Refusal::cases()), $messages);
        self::assertSame('The carrier cannot carry this much.', InventoryRefused::because(Refusal::OverCapacity)->getMessage());
        self::assertSame('An inventory already holds this reference.', InventoryRefused::because(Refusal::AlreadyOpen)->getMessage());
    }

    public function testUnknownInventoriesAndItemsAreNamed(): void
    {
        self::assertSame('No inventory is known as "inventory:9".', UnknownInventory::named('inventory:9')->getMessage());
        self::assertSame('The inventory "inventory:9" holds no item under the key "rope".', UnknownInventory::item('inventory:9', 'rope')->getMessage());
        self::assertInstanceOf(InventoryException::class, InvalidInventory::because('x'));
        self::assertSame('x', InvalidInventory::because('x')->getMessage());
    }

    public function testTextAcceptsDottedKeysAndBoundNames(): void
    {
        self::assertSame('arrows.silver_2-b', Text::key('arrows.silver_2-b', 'an item'));
        self::assertSame(str_repeat('a', 96), Text::key(str_repeat('a', 96), 'an item'));
        self::assertSame('Épée', Text::name('Épée', 'A name', 4));
        self::assertSame(5, Text::between(5, 5, 5, 'A number'));
    }
}
