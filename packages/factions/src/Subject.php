<?php

declare(strict_types=1);

namespace Tilttek\Factions;

use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;

/**
 * Whom a faction stands with: the party as a whole, or one character by their identifier. Its key, "party" or
 * "character:" and the identifier, is what a store keeps.
 */
final readonly class Subject
{
    public const string PARTY = 'party';

    public const string CHARACTER_PREFIX = 'character:';

    /** "party", or "character:" and the character's identifier. */
    public string $key;

    private function __construct(public ?string $character)
    {
        $this->key = null === $character ? self::PARTY : self::CHARACTER_PREFIX.$character;
    }

    public static function party(): self
    {
        return new self(null);
    }

    /**
     * @throws InvalidRoster when the identifier is not one
     */
    public static function character(string $character): self
    {
        return new self(Identifier::check($character, 'a character'));
    }

    /**
     * The subject of a key a store kept.
     *
     * @throws InvalidRoster when the key is neither "party" nor "character:" and an identifier
     */
    public static function fromKey(string $key): self
    {
        if (self::PARTY === $key) {
            return self::party();
        }

        if (!str_starts_with($key, self::CHARACTER_PREFIX)) {
            throw InvalidRoster::because('The key of a subject is "party", or "character:" and an identifier.');
        }

        return self::character(substr($key, \strlen(self::CHARACTER_PREFIX)));
    }

    public function isParty(): bool
    {
        return null === $this->character;
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }
}
