<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Inventory\Contract\CarrierCapacity;
use Tilttek\Inventory\Contract\InventoryObserver;
use Tilttek\Inventory\Contract\InventoryStore;
use Tilttek\Inventory\Contract\RuleBook;
use Tilttek\Inventory\Exception\InvalidInventory;
use Tilttek\Inventory\Exception\InventoryRefused;
use Tilttek\Inventory\Exception\UnknownInventory;
use Tilttek\Inventory\Rules\DefaultCapacity;
use Tilttek\Inventory\Rules\StaticRuleBook;

/**
 * The inventories of the application's characters and parties during play: items with their quantities and burdens,
 * containers, who carries what, the load of each carrier by the rules of the game system, and a log of every change.
 *
 * ```php
 * $inventories->open(ResourceReference::of('inventory', '7'), ResourceReference::of('party', '3'), 'The party\'s gear', 'srd-5.2');
 * $inventories->add($inventory, 'backpack', 'Backpack', 1, new Burden(5), Place::carriedBy($ada), new Container(maxWeight: 30));
 * $inventories->add($inventory, 'rope', 'Hempen rope', 1, new Burden(10), Place::inside('backpack'));
 * $inventories->move($inventory, 'arrows', Place::carriedBy($bo), quantity: 10, as: 'arrows.bo');
 * $inventories->load($inventory, $ada)?->band?->key;    // 'unencumbered'
 * ```
 *
 * The library decides no permission: the application checks who may act before it calls, and gives the acting
 * reference as `$by`, which the log and the events carry.
 */
final readonly class Inventories
{
    public const int MAX_LOG_READ = 1000;

    /** @var list<InventoryObserver> */
    private array $observers;

    /**
     * @param iterable<InventoryObserver> $observers
     */
    public function __construct(
        private InventoryStore $store,
        private ClockInterface $clock,
        private RuleBook $rules = new StaticRuleBook(),
        private CarrierCapacity $capacity = new DefaultCapacity(),
        private Limits $limits = new Limits(),
        iterable $observers = [],
    ) {
        $list = [];

        foreach ($observers as $observer) {
            $list[] = $observer;
        }

        $this->observers = $list;
    }

    /**
     * Opens an inventory for an owner, under the reference the application gives, with the key of the game system
     * whose carrying rules weigh it, or none.
     */
    public function open(ResourceReference $inventory, ResourceReference $owner, string $name, ?string $system = null, ?ResourceReference $by = null): Inventory
    {
        $opened = new Inventory($inventory, $owner, $name, $system, $this->clock->now());

        if ($this->store->inventory($inventory->toString()) instanceof Inventory) {
            throw InventoryRefused::because(Refusal::AlreadyOpen);
        }

        if (\count($this->store->inventoriesOf($owner->toString())) >= $this->limits->inventoriesPerOwner) {
            throw InventoryRefused::because(Refusal::TooManyInventories);
        }

        $this->store->saveInventory($opened);
        $this->record($inventory, Change::Opened, null, null, null, null, $by, null);

        return $opened;
    }

    /**
     * @throws UnknownInventory
     */
    public function inventory(ResourceReference $inventory): Inventory
    {
        return $this->store->inventory($inventory->toString()) ?? throw UnknownInventory::named($inventory->toString());
    }

    /**
     * The owner's inventories, the earliest opened first.
     *
     * @return list<Inventory>
     */
    public function inventoriesOf(ResourceReference $owner): array
    {
        return $this->store->inventoriesOf($owner->toString());
    }

    public function rename(ResourceReference $inventory, string $name, ?ResourceReference $by = null): Inventory
    {
        $renamed = $this->inventory($inventory)->renamed($name);
        $this->store->saveInventory($renamed);
        $this->record($inventory, Change::Updated, null, null, null, null, $by, null);

        return $renamed;
    }

    /**
     * Weighs the inventory by another game system's carrying rules, or by none. Nothing is refused for a load the new
     * rules find too heavy: the loads tell it, and the next act that adds to that carrier is refused.
     */
    public function useSystem(ResourceReference $inventory, ?string $system, ?ResourceReference $by = null): Inventory
    {
        $changed = $this->inventory($inventory)->withSystem($system);
        $this->store->saveInventory($changed);
        $this->record($inventory, Change::Updated, null, null, null, null, $by, null);

        return $changed;
    }

    /**
     * Closes the inventory with its items and its log.
     */
    public function close(ResourceReference $inventory, ?ResourceReference $by = null): void
    {
        $this->inventory($inventory);
        $this->store->removeInventory($inventory->toString());
        $this->tell($inventory, Change::Closed, null, $by);
    }

    /**
     * Adds an item under a new key, in the stash when no place is given.
     */
    public function add(
        ResourceReference $inventory,
        string $key,
        string $name,
        int $quantity = 1,
        Burden $burden = new Burden(),
        ?Place $place = null,
        ?Container $container = null,
        ?ResourceReference $by = null,
        ?string $reason = null,
    ): Item {
        $opened = $this->inventory($inventory);
        $place ??= Place::stash();
        $item = new Item($inventory, $key, $name, $quantity, $burden, $place, $this->clock->now(), $container);
        $this->checkReason($reason);
        $this->vacant($inventory, $key);
        $packing = $this->packing($inventory);
        $this->reach($packing, $item, $place);
        $this->check($opened, $packing->with($item), $place);

        $this->store->saveItem($item);
        $this->record($inventory, Change::Added, $key, $quantity, null, $place->toString(), $by, $reason);

        return $item;
    }

    /**
     * Adds units to an item where it is: found, bought, crafted.
     */
    public function increase(ResourceReference $inventory, string $key, int $quantity, ?ResourceReference $by = null, ?string $reason = null): Item
    {
        $item = $this->item($inventory, $key);
        $this->units($quantity);
        $this->checkReason($reason);

        if ($item->isContainer()) {
            throw InventoryRefused::because(Refusal::SingleContainer);
        }

        $increased = $item->withQuantity($item->quantity + $quantity);
        $this->check($this->inventory($inventory), $this->packing($inventory)->with($increased), $item->place);

        $this->store->saveItem($increased);
        $this->record($inventory, Change::Increased, $key, $quantity, null, $item->place->toString(), $by, $reason);

        return $increased;
    }

    /**
     * Takes units of an item away — used, sold, lost —; the entry goes when none is left, and null is returned. A
     * container goes only once empty.
     */
    public function decrease(ResourceReference $inventory, string $key, int $quantity, ?ResourceReference $by = null, ?string $reason = null): ?Item
    {
        $item = $this->item($inventory, $key);
        $this->units($quantity);
        $this->checkReason($reason);

        if ($quantity > $item->quantity) {
            throw InventoryRefused::because(Refusal::NotEnough);
        }

        if ($quantity < $item->quantity) {
            $left = $item->withQuantity($item->quantity - $quantity);
            $this->store->saveItem($left);
            $this->record($inventory, Change::Decreased, $key, $quantity, $item->place->toString(), null, $by, $reason);

            return $left;
        }

        $this->remove($inventory, $key, $by, $reason);

        return null;
    }

    /**
     * Takes the whole entry away; a container goes only once empty.
     */
    public function remove(ResourceReference $inventory, string $key, ?ResourceReference $by = null, ?string $reason = null): void
    {
        $item = $this->item($inventory, $key);
        $this->checkReason($reason);

        if ([] !== $this->packing($inventory)->children($key)) {
            throw InventoryRefused::because(Refusal::ContainerNotEmpty);
        }

        $this->store->removeItem($inventory->toString(), $key);
        $this->record($inventory, Change::Removed, $key, $item->quantity, $item->place->toString(), null, $by, $reason);
    }

    /**
     * Moves an item elsewhere in the inventory — to a carrier, into a container, to the stash —, a container with what
     * it holds. Part of a stack moves under a new key, `$as`; a moved item is no longer equipped.
     */
    public function move(
        ResourceReference $inventory,
        string $key,
        Place $to,
        ?int $quantity = null,
        ?string $as = null,
        ?ResourceReference $by = null,
        ?string $reason = null,
    ): Item {
        $opened = $this->inventory($inventory);
        $item = $this->item($inventory, $key);
        $this->checkReason($reason);
        $quantity = $this->part($item, $quantity, $as);
        $packing = $this->packing($inventory);

        if (null === $as) {
            $moved = $item->placed($to);
            $this->reach($packing, $item, $to);
            $this->check($opened, $packing->with($moved), $to);
            $this->store->saveItem($moved);
        } else {
            $moved = $item->copiedAs($inventory, $as, $quantity, $to, $this->clock->now());
            $this->vacant($inventory, $as);
            $this->reach($packing, $moved, $to);
            $left = $item->withQuantity($item->quantity - $quantity);
            $this->check($opened, $packing->with($left)->with($moved), $to);
            $this->store->saveItem($left);
            $this->store->saveItem($moved);
        }

        $this->record($inventory, Change::Moved, $moved->key, $quantity, $item->place->toString(), $to->toString(), $by, $reason);

        return $moved;
    }

    /**
     * Its carrier equips the item: wields, wears, readies it. Only an item directly on a carrier is equipped.
     */
    public function equip(ResourceReference $inventory, string $key, ?ResourceReference $by = null): Item
    {
        $item = $this->item($inventory, $key);

        if (!$item->place->carrier instanceof ResourceReference) {
            throw InventoryRefused::because(Refusal::NotCarried);
        }

        return $this->setEquipped($inventory, $item, true, Change::Equipped, $by);
    }

    public function unequip(ResourceReference $inventory, string $key, ?ResourceReference $by = null): Item
    {
        return $this->setEquipped($inventory, $this->item($inventory, $key), false, Change::Unequipped, $by);
    }

    /**
     * Moves an item, or part of a stack, to another inventory — a character's share of the party's loot, the gear left
     * at the inn —, under its key or `$as`, in the stash when no place is given. A container moves only once empty.
     */
    public function transfer(
        ResourceReference $from,
        string $key,
        ResourceReference $to,
        ?Place $place = null,
        ?int $quantity = null,
        ?string $as = null,
        ?ResourceReference $by = null,
        ?string $reason = null,
    ): Item {
        if ($from->equals($to)) {
            throw InvalidInventory::because('A transfer goes to another inventory; move the item instead.');
        }

        $item = $this->item($from, $key);
        $quantity = $this->units($quantity ?? $item->quantity);
        $this->checkReason($reason);
        $target = $this->inventory($to);
        $place ??= Place::stash();

        if ($quantity > $item->quantity) {
            throw InventoryRefused::because(Refusal::NotEnough);
        }

        if ([] !== $this->packing($from)->children($key)) {
            throw InventoryRefused::because(Refusal::ContainerNotEmpty);
        }

        $arrived = $item->copiedAs($to, $as ?? $key, $quantity, $place, $this->clock->now());
        $this->vacant($to, $arrived->key);
        $packing = $this->packing($to);
        $this->reach($packing, $arrived, $place);
        $this->check($target, $packing->with($arrived), $place);

        $this->store->saveItem($arrived);

        if ($quantity === $item->quantity) {
            $this->store->removeItem($from->toString(), $key);
        } else {
            $this->store->saveItem($item->withQuantity($item->quantity - $quantity));
        }

        $this->record($from, Change::TransferredOut, $key, $quantity, $item->place->toString(), $to->toString(), $by, $reason);
        $this->record($to, Change::TransferredIn, $arrived->key, $quantity, $from->toString(), $place->toString(), $by, $reason);

        return $arrived;
    }

    /**
     * @throws UnknownInventory
     */
    public function item(ResourceReference $inventory, string $key): Item
    {
        $this->inventory($inventory);

        return $this->store->item($inventory->toString(), $key) ?? throw UnknownInventory::item($inventory->toString(), $key);
    }

    /**
     * The inventory's items, the earliest added first.
     *
     * @return list<Item>
     */
    public function items(ResourceReference $inventory): array
    {
        $this->inventory($inventory);

        return $this->store->items($inventory->toString());
    }

    /**
     * The items directly at a place — in the stash, on a carrier, inside a container —, the earliest added first.
     *
     * @return list<Item>
     */
    public function itemsAt(ResourceReference $inventory, Place $place): array
    {
        return array_values(array_filter($this->items($inventory), static fn (Item $item): bool => $item->place->equals($place)));
    }

    /**
     * What a carrier carries in the inventory, weighed by its system's rules, or null when the inventory has no system
     * or its system declares no carrying rules.
     */
    public function load(ResourceReference $inventory, ResourceReference $carrier): ?Load
    {
        $opened = $this->inventory($inventory);
        $rules = $this->rulesOf($opened);

        return $rules instanceof CarryRules ? $this->weigh($opened, $this->packing($inventory), $carrier, $rules) : null;
    }

    /**
     * The load of each carrier holding something in the inventory, in the order of their references; none when the
     * inventory is not weighed.
     *
     * @return list<Load>
     */
    public function loads(ResourceReference $inventory): array
    {
        $opened = $this->inventory($inventory);
        $rules = $this->rulesOf($opened);

        if (!$rules instanceof CarryRules) {
            return [];
        }

        $packing = $this->packing($inventory);

        return array_map(fn (ResourceReference $carrier): Load => $this->weigh($opened, $packing, $carrier, $rules), $packing->carriers());
    }

    /**
     * What a container holds against its bounds.
     */
    public function fill(ResourceReference $inventory, string $key): Fill
    {
        $item = $this->item($inventory, $key);

        if (!$item->isContainer()) {
            throw InventoryRefused::because(Refusal::NotAContainer);
        }

        return $this->packing($inventory)->fill($item);
    }

    /**
     * The newest entries of the inventory's log, 1 to 1000, the newest first.
     *
     * @return list<LogEntry>
     */
    public function log(ResourceReference $inventory, int $limit = 50): array
    {
        $this->inventory($inventory);
        Text::between($limit, 1, self::MAX_LOG_READ, 'The log entries read');

        return $this->store->log($inventory->toString(), $limit);
    }

    /**
     * Erases a reference: what it carried, in any inventory, goes to the stash, unequipped, and the logs keep no trace
     * of it — the acting reference dropped, a place on it written `carrier`. The inventories it owns stay: the
     * application closes them, from `inventoriesOf()`, when they go with it.
     */
    public function forget(ResourceReference $reference): void
    {
        foreach ($this->store->carriedBy($reference->toString()) as $item) {
            $this->store->saveItem($item->placed(Place::stash()));
            $this->record($item->inventory, Change::Forgotten, $item->key, $item->quantity, 'carrier', 'stash', null, null);
        }

        $this->store->eraseFromLog($reference->toString());
    }

    private function setEquipped(ResourceReference $inventory, Item $item, bool $equipped, Change $change, ?ResourceReference $by): Item
    {
        if ($item->equipped === $equipped) {
            return $item;
        }

        $changed = $item->withEquipped($equipped);
        $this->store->saveItem($changed);
        $this->record($inventory, $change, $item->key, null, null, null, $by, null);

        return $changed;
    }

    /**
     * The units a move takes: the whole stack when no quantity is given; part of it only under a new key.
     */
    private function part(Item $item, ?int $quantity, ?string $as): int
    {
        $quantity = $this->units($quantity ?? $item->quantity);

        if ($quantity > $item->quantity) {
            throw InventoryRefused::because(Refusal::NotEnough);
        }

        if ($quantity < $item->quantity && null === $as) {
            throw InvalidInventory::because(\sprintf('Part of "%s" moves under a new key; none is given.', $item->key));
        }

        if ($quantity === $item->quantity && null !== $as) {
            throw InvalidInventory::because(\sprintf('A new key is given only when part of "%s" moves.', $item->key));
        }

        return $quantity;
    }

    private function units(int $quantity): int
    {
        return Text::between($quantity, 1, Item::MAX_QUANTITY, 'A quantity');
    }

    private function checkReason(?string $reason): void
    {
        if (null !== $reason) {
            Text::name($reason, 'A reason', LogEntry::MAX_REASON_LENGTH);
        }
    }

    /**
     * Checks that the key is free in the inventory and that one more item fits.
     */
    private function vacant(ResourceReference $inventory, string $key): void
    {
        if ($this->store->item($inventory->toString(), $key) instanceof Item) {
            throw InventoryRefused::because(Refusal::ItemExists);
        }

        if ($this->store->countItems($inventory->toString()) >= $this->limits->itemsPerInventory) {
            throw InventoryRefused::because(Refusal::TooManyItems);
        }
    }

    /**
     * Checks that the item can go to the place: an existing container, not inside itself, not too deep.
     */
    private function reach(Packing $packing, Item $item, Place $place): void
    {
        if (null !== $place->container) {
            $container = $packing->item($place->container) ?? throw UnknownInventory::item($item->inventory->toString(), $place->container);

            if (!$container->isContainer()) {
                throw InventoryRefused::because(Refusal::NotAContainer);
            }

            if ($container->key === $item->key || $packing->isWithin($container->place, $item)) {
                throw InventoryRefused::because(Refusal::ContainerCycle);
            }
        }

        if ($packing->depthOf($place) + $packing->height($item) > $this->limits->containerDepth) {
            throw InventoryRefused::because(Refusal::TooDeep);
        }
    }

    /**
     * Checks, once the act is applied, the containers the place is in and the load of the carrier who holds them.
     */
    private function check(Inventory $inventory, Packing $after, Place $place): void
    {
        foreach ($after->chain($place) as $container) {
            $fill = $after->fill($container);
            $bounds = $container->container;

            if (!$bounds instanceof Container || !$bounds->holds($fill->weight, $fill->slots)) {
                throw InventoryRefused::because(Refusal::ContainerFull);
            }
        }

        $carrier = $after->carrierOf($place);
        $rules = $this->rulesOf($inventory);

        if ($carrier instanceof ResourceReference && $rules instanceof CarryRules && $rules->refuseOverCapacity && $this->weigh($inventory, $after, $carrier, $rules)->isOver()) {
            throw InventoryRefused::because(Refusal::OverCapacity);
        }
    }

    private function weigh(Inventory $inventory, Packing $packing, ResourceReference $carrier, CarryRules $rules): Load
    {
        $capacity = CarryRules::capacity($this->capacity->capacity($inventory, $carrier, $rules));
        $carried = $packing->carried($carrier, $rules->measure);

        return new Load($carrier, $rules->measure, $carried, $capacity, $rules->bandFor($carried, $capacity), $rules->lastBand()->limitFor($capacity));
    }

    private function rulesOf(Inventory $inventory): ?CarryRules
    {
        return null === $inventory->system ? null : $this->rules->rules($inventory->system);
    }

    private function packing(ResourceReference $inventory): Packing
    {
        return new Packing($this->store->items($inventory->toString()));
    }

    private function record(ResourceReference $inventory, Change $change, ?string $item, ?int $quantity, ?string $from, ?string $to, ?ResourceReference $by, ?string $reason): void
    {
        $now = $this->clock->now();
        $this->store->appendLog(new LogEntry($inventory->toString(), $change, $item, $quantity, $from, $to, $by?->toString(), $reason, $now), $this->limits->logPerInventory);
        $this->tell($inventory, $change, $item, $by);
    }

    private function tell(ResourceReference $inventory, Change $change, ?string $item, ?ResourceReference $by): void
    {
        $event = new InventoryChanged($inventory->toString(), $change, $item, $by?->toString(), $this->clock->now());

        foreach ($this->observers as $observer) {
            $observer->changed($event);
        }
    }
}
