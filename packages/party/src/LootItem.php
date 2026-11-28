<?php

declare(strict_types=1);

namespace Tilttek\Party;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Party\Exception\InvalidParty;

/**
 * Loot the party holds in common, under a key the application gives — `loot.rope`, `sword-of-dawn` —, with its name,
 * how many there are, and the member who carries it; loot no member carries lies in the party's stash.
 *
 * The name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces; the quantity is 1 to
 * 1,000,000.
 */
final readonly class LootItem
{
    public const int MAX_NAME_LENGTH = 100;

    public const int MAX_QUANTITY = 1_000_000;

    public function __construct(
        public ResourceReference $party,
        public string $key,
        public string $name,
        public int $quantity,
        public ?ResourceReference $holder,
        public DateTimeImmutable $addedAt,
    ) {
        Text::key($key, 'a loot entry');
        Text::name($name, 'A loot name', self::MAX_NAME_LENGTH);

        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw InvalidParty::because(\sprintf('A loot quantity is 1 to %d; %d is not.', self::MAX_QUANTITY, $quantity));
        }
    }

    /**
     * The loot carried by another member, or put back in the stash.
     */
    public function heldBy(?ResourceReference $holder): self
    {
        return new self($this->party, $this->key, $this->name, $this->quantity, $holder, $this->addedAt);
    }

    public function withQuantity(int $quantity): self
    {
        return new self($this->party, $this->key, $this->name, $quantity, $this->holder, $this->addedAt);
    }

    public function isInStash(): bool
    {
        return !$this->holder instanceof ResourceReference;
    }
}
