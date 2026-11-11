<?php

declare(strict_types=1);

namespace Tilttek\LootTables;

use Tilttek\LootTables\Exception\InvalidLoot;

/**
 * A table rolled with one die: as many entries as the die has sides, the entry of face 1 first. A table of six
 * entries is rolled with a d6, of ten with a d10, of twenty with a d20.
 */
final readonly class LootTable
{
    /** The fewest entries, for a die of two sides. */
    public const int MIN_ENTRIES = 2;

    /** The most entries: the largest die `tilttek/dice` rolls by default. */
    public const int MAX_ENTRIES = 1000;

    /** The longest name of a table, in characters. */
    public const int MAX_NAME = 200;

    /**
     * @var non-empty-list<LootEntry>
     */
    public array $entries;

    /**
     * @param string          $key     1 to 64 lowercase letters, digits, "_" or "-", starting with a letter or a digit
     * @param string          $name    1 to 200 characters of UTF-8, not blank
     * @param list<LootEntry> $entries 2 to 1000, each key once, in the order of the faces
     *
     * @throws InvalidLoot
     */
    public function __construct(
        public string $key,
        public string $name,
        array $entries,
    ) {
        if (1 !== preg_match(LootEntry::KEY_PATTERN, $key)) {
            throw InvalidLoot::because(\sprintf('"%s" is not the key of a table: 1 to 64 lowercase letters, digits, "_" or "-".', $key));
        }

        LootEntry::checkName($name, \sprintf('The name of the table %s', $key), self::MAX_NAME);
        $count = \count($entries);

        if ($count < self::MIN_ENTRIES || $count > self::MAX_ENTRIES) {
            throw InvalidLoot::because(\sprintf('The table %s has %d entries; a table has %d to %d, one per side of its die.', $key, $count, self::MIN_ENTRIES, self::MAX_ENTRIES));
        }

        $keys = [];

        foreach ($entries as $entry) {
            if (isset($keys[$entry->key])) {
                throw InvalidLoot::because(\sprintf('The table %s lists the entry %s twice.', $key, $entry->key));
            }

            $keys[$entry->key] = $entry;
        }

        $this->entries = $entries;
    }

    /**
     * A table of texts, each an entry keyed by its face: the list a text is drawn from.
     *
     * @param list<string> $texts 2 to 1000, each 1 to 1000 characters
     *
     * @throws InvalidLoot
     */
    public static function ofTexts(string $key, string $name, array $texts): self
    {
        $entries = [];

        foreach ($texts as $index => $text) {
            $entries[] = new LootEntry((string) ($index + 1), $text);
        }

        return new self($key, $name, $entries);
    }

    /**
     * The sides of the die the table is rolled with: its number of entries.
     */
    public function sides(): int
    {
        return \count($this->entries);
    }

    /**
     * @param int $face from 1 to the sides of the die
     *
     * @throws InvalidLoot when the die has no such face
     */
    public function entry(int $face): LootEntry
    {
        return $this->entries[$face - 1] ?? throw InvalidLoot::because(\sprintf('The table %s is rolled with a d%d; %d is not one of its faces.', $this->key, $this->sides(), $face));
    }
}
