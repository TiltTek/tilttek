<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Exception\InvalidEntry;

/**
 * The identifier of a game in an open database, and nothing else of it.
 */
final readonly class ExternalId
{
    /**
     * @throws InvalidEntry when the value is not an identifier of the database
     */
    public function __construct(public IdentifierScheme $scheme, public string $value)
    {
        if (!$scheme->accepts($value)) {
            throw InvalidEntry::because(\sprintf('"%s" is not an identifier of %s.', mb_strimwidth($value, 0, 40, '…'), $scheme->value));
        }
    }

    public static function boardGameGeek(int|string $value): self
    {
        return new self(IdentifierScheme::BoardGameGeek, (string) $value);
    }

    public static function rpgGeek(int|string $value): self
    {
        return new self(IdentifierScheme::RpgGeek, (string) $value);
    }

    public static function wikidata(string $value): self
    {
        return new self(IdentifierScheme::Wikidata, $value);
    }

    /**
     * The page of the game on its database, to link to.
     */
    public function link(): string
    {
        return $this->scheme->link($this->value);
    }

    /**
     * The scheme and the value, as `boardgamegeek:13`: unique in the catalogue.
     */
    public function key(): string
    {
        return $this->scheme->value.':'.$this->value;
    }

    public function equals(self $other): bool
    {
        return $this->scheme === $other->scheme && $this->value === $other->value;
    }
}
