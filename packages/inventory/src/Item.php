<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Inventory\Exception\InvalidInventory;

/**
 * An entry of an inventory, under a key the application gives — `rope`, `arrows.silver` —: its name, how many there
 * are, its burden, where it is, whether its carrier has it equipped, and, for a container, what it holds.
 *
 * The name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces; the quantity is 1 to
 * 1,000,000, and always 1 for a container. Only an item on a carrier is equipped.
 */
final readonly class Item
{
    public const int MAX_NAME_LENGTH = 100;

    public const int MAX_QUANTITY = 1_000_000;

    public function __construct(
        public ResourceReference $inventory,
        public string $key,
        public string $name,
        public int $quantity,
        public Burden $burden,
        public Place $place,
        public DateTimeImmutable $addedAt,
        public ?Container $container = null,
        public bool $equipped = false,
    ) {
        Text::key($key, 'an item');
        Text::name($name, 'An item name', self::MAX_NAME_LENGTH);
        Text::between($quantity, 1, self::MAX_QUANTITY, 'An item quantity');

        if ($container instanceof Container && 1 !== $quantity) {
            throw InvalidInventory::because(\sprintf('A container is a single item; the quantity of "%s" is %d.', $key, $quantity));
        }

        if ($equipped && !$place->carrier instanceof ResourceReference) {
            throw InvalidInventory::because(\sprintf('Only an item on a carrier is equipped; "%s" is not on one.', $key));
        }
    }

    public function isContainer(): bool
    {
        return $this->container instanceof Container;
    }

    /**
     * The item somewhere else; it is no longer equipped.
     */
    public function placed(Place $place): self
    {
        return new self($this->inventory, $this->key, $this->name, $this->quantity, $this->burden, $place, $this->addedAt, $this->container);
    }

    public function withQuantity(int $quantity): self
    {
        return new self($this->inventory, $this->key, $this->name, $quantity, $this->burden, $this->place, $this->addedAt, $this->container, $this->equipped);
    }

    public function withEquipped(bool $equipped): self
    {
        return new self($this->inventory, $this->key, $this->name, $this->quantity, $this->burden, $this->place, $this->addedAt, $this->container, $equipped);
    }

    /**
     * A copy under another key, in another inventory or at another moment, never equipped: the part of a stack moved
     * apart, or an item transferred.
     */
    public function copiedAs(ResourceReference $inventory, string $key, int $quantity, Place $place, DateTimeImmutable $at): self
    {
        return new self($inventory, $key, $this->name, $quantity, $this->burden, $place, $at, $this->container);
    }

    /**
     * What these units weigh, or the slots they take, without what they hold.
     */
    public function own(Measure $measure): int
    {
        return $this->burden->of($this->quantity, $measure);
    }
}
