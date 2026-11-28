<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

use Phil\Resource\ResourceReference;
use Tilttek\Inventory\Exception\InvalidInventory;

/**
 * Where an item is: in the stash — the camp, the cart, the bank, carried by no one —, on a carrier, or inside a
 * container of the same inventory, named by its key. An item inside a container is carried by whoever carries the
 * container.
 */
final readonly class Place
{
    private function __construct(
        public ?ResourceReference $carrier,
        public ?string $container,
    ) {
    }

    public static function stash(): self
    {
        return new self(null, null);
    }

    /**
     * On a carrier, under the reference the application gives it: `character:12`, `companion:3`.
     */
    public static function carriedBy(ResourceReference $carrier): self
    {
        return new self($carrier, null);
    }

    /**
     * Inside the container under that key.
     */
    public static function inside(string $container): self
    {
        return new self(null, Text::key($container, 'a container'));
    }

    public function isStash(): bool
    {
        return !$this->carrier instanceof ResourceReference && null === $this->container;
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    /**
     * `stash`, `carrier:` then the carrier's reference, or `container:` then the container's key: the form the log and
     * a store keep.
     */
    public function toString(): string
    {
        if ($this->carrier instanceof ResourceReference) {
            return 'carrier:'.$this->carrier->toString();
        }

        if (null !== $this->container) {
            return 'container:'.$this->container;
        }

        return 'stash';
    }

    /**
     * The place written by `toString()`.
     */
    public static function fromString(string $place): self
    {
        if ('stash' === $place) {
            return self::stash();
        }

        if (str_starts_with($place, 'carrier:')) {
            return self::carriedBy(ResourceReference::fromString(substr($place, 8)));
        }

        if (str_starts_with($place, 'container:')) {
            return self::inside(substr($place, 10));
        }

        throw InvalidInventory::because(\sprintf('A place is "stash", "carrier:" then a reference, or "container:" then a key; "%s" is not.', $place));
    }
}
