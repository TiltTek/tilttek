<?php

declare(strict_types=1);

namespace Tilttek\Party;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A party: the group of characters that plays a campaign together, named by the reference the application gives it —
 * `party:7` —, with the campaign it plays, its name, and the key of the game system whose roles its composition is read
 * against, `srd-5.2`, when it has one.
 *
 * The name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.
 */
final readonly class Party
{
    public const int MAX_NAME_LENGTH = 100;

    public function __construct(
        public ResourceReference $reference,
        public ResourceReference $campaign,
        public string $name,
        public ?string $system,
        public DateTimeImmutable $formedAt,
    ) {
        Text::name($name, 'A party name', self::MAX_NAME_LENGTH);

        if (null !== $system) {
            Text::key($system, 'a game system');
        }
    }

    public function renamed(string $name): self
    {
        return new self($this->reference, $this->campaign, $name, $this->system, $this->formedAt);
    }

    /**
     * The party with another game system, or none.
     */
    public function withSystem(?string $system): self
    {
        return new self($this->reference, $this->campaign, $this->name, $system, $this->formedAt);
    }
}
