<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * An inventory: what a character or a party holds during play, named by the reference the application gives it —
 * `inventory:7` —, with its owner — `character:12`, `party:3` —, its name, and the key of the game system whose
 * carrying rules weigh it, `srd-5.2`, when it has one.
 *
 * The name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.
 */
final readonly class Inventory
{
    public const int MAX_NAME_LENGTH = 100;

    public function __construct(
        public ResourceReference $reference,
        public ResourceReference $owner,
        public string $name,
        public ?string $system,
        public DateTimeImmutable $openedAt,
    ) {
        Text::name($name, 'An inventory name', self::MAX_NAME_LENGTH);

        if (null !== $system) {
            Text::key($system, 'a game system');
        }
    }

    public function renamed(string $name): self
    {
        return new self($this->reference, $this->owner, $name, $this->system, $this->openedAt);
    }

    /**
     * The inventory weighed by another game system's rules, or by none.
     */
    public function withSystem(?string $system): self
    {
        return new self($this->reference, $this->owner, $this->name, $system, $this->openedAt);
    }
}
