<?php

declare(strict_types=1);

namespace Tilttek\Factions;

use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\Internal\Text;

/**
 * How two factions of a roster stand with each other, the same both ways: the pair is kept with the lower key first,
 * whatever order it was given in. A secret relation is shown to the game master alone.
 */
final readonly class Relation
{
    /** The longest note, in characters. */
    public const int MAX_NOTE = 200;

    /** The key of the pair that sorts first. */
    public string $first;

    /** The key of the pair that sorts second. */
    public string $second;

    /** The key of the pair: the two keys, the lower first, joined by a space no key holds. */
    public string $key;

    public string $note;

    /**
     * @param string $faction one faction, by its key
     * @param string $other   the other faction, by its key
     * @param string $note    one line of up to `MAX_NOTE` characters, possibly empty
     * @param bool   $secret  true when the players do not know of it
     *
     * @throws InvalidRoster when a key or the note is out of its shape or its bounds, or both keys are the same
     */
    public function __construct(string $faction, string $other, public Stance $stance, string $note = '', public bool $secret = false)
    {
        Identifier::key($faction, 'a faction');
        Identifier::key($other, 'a faction');

        if ($faction === $other) {
            throw InvalidRoster::because(\sprintf('The faction %s has no relation with itself.', $faction));
        }

        // A key starts with a letter, so PHP compares two keys as strings, never as numbers.
        $this->first = min($faction, $other);
        $this->second = max($faction, $other);
        $this->key = $this->first.' '.$this->second;
        $this->note = Text::line($note, self::MAX_NOTE, 'note of a relation', false);
    }

    /**
     * The key of the pair: the two keys, the lower first, joined by a space no key holds.
     */
    public static function pair(string $faction, string $other): string
    {
        return min($faction, $other).' '.max($faction, $other);
    }

    public function involves(string $faction): bool
    {
        return $faction === $this->first || $faction === $this->second;
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key && $this->stance === $other->stance && $this->note === $other->note && $this->secret === $other->secret;
    }
}
