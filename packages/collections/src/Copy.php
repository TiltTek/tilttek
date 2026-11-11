<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;

/**
 * One copy of an item, owned by a member: a member with two copies of an item has a double. Its identifier derives from
 * the key it was given under, so that giving it again — a task run twice, a rebuild — finds it rather than adding
 * another. It remembers how it came, when, whether it may change hands, and whether it has changed hands already.
 */
final readonly class Copy
{
    /**
     * The kind of the resource reference a copy is known by outside the library — to a trade, for instance.
     */
    public const string KIND = 'collection-copy';

    public function __construct(
        public string $id,
        public string $item,
        public ResourceReference $owner,
        public Source $source,
        public string $key,
        public DateTimeImmutable $acquiredAt,
        public bool $tradable = true,
        public bool $moved = false,
    ) {
    }

    /**
     * The identifier of the copy given under a key: 32 hexadecimal digits.
     */
    public static function idFor(CollectionKey $key): string
    {
        return substr(hash('sha256', $key->value), 0, 32);
    }

    public function reference(): ResourceReference
    {
        return ResourceReference::of(self::KIND, $this->id);
    }

    /**
     * The same copy in another member's hands.
     */
    public function movedTo(ResourceReference $owner): self
    {
        return new self($this->id, $this->item, $owner, $this->source, $this->key, $this->acquiredAt, $this->tradable, true);
    }

    /**
     * @return array{id: string, item: string, owner: string, source: string, key: string, acquired_at: string, tradable: bool, moved: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'item' => $this->item,
            'owner' => $this->owner->toString(),
            'source' => $this->source->value,
            'key' => $this->key,
            'acquired_at' => $this->acquiredAt->format(DateTimeInterface::ATOM),
            'tradable' => $this->tradable,
            'moved' => $this->moved,
        ];
    }
}
