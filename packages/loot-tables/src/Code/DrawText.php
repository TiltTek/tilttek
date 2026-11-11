<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Code;

use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Exception\InvalidCode;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;

/**
 * A result written as one line a receipt shows and a program reads back: the faces joined by `-`, a colon, and the
 * names of the entries joined by commas — `3-5-2-6-1-4: Sword, Helmet, Boots, Shield, Ring, Cloak`. Reading takes
 * the faces alone, so a name renamed since still reads.
 */
final readonly class DrawText
{
    /** The longest line, in characters. */
    public const int MAX_LENGTH = 1000;

    private const string FACES = '/^(?<faces>[1-9][0-9]{0,3}(?:-[1-9][0-9]{0,3}){0,9}): /';

    public static function write(LootResult $result): string
    {
        return implode('-', $result->faces()).': '.implode(', ', array_map(static fn (LootEntry $entry): string => $entry->name, $result->entries()));
    }

    /**
     * @throws InvalidCode when the line does not start with one face per die of the box, each a face of its die
     */
    public static function read(LootBox $box, string $text, DrawSource $source): LootResult
    {
        if (1 !== preg_match(self::FACES, $text, $match)) {
            throw InvalidCode::because('A draw text starts with its faces joined by "-", then ": ".');
        }

        $faces = array_map(intval(...), explode('-', $match['faces']));

        if (\count($faces) !== \count($box->tables)) {
            throw InvalidCode::because(\sprintf('A draw text of the box %s has %d faces, one per die.', $box->key, \count($box->tables)));
        }

        foreach ($box->dice() as $index => $sides) {
            if ($faces[$index] > $sides) {
                throw InvalidCode::because(\sprintf('Face %d of the draw text is not a face of a d%d.', $index + 1, $sides));
            }
        }

        return LootResult::ofFaces($box, $faces, $source);
    }

    /**
     * The longest line a draw of the box may write, in characters.
     */
    public static function longest(LootBox $box): int
    {
        // The faces joined by "-", then ": ", then the names joined by ", ".
        $length = 3 * \count($box->tables) - 1;

        foreach ($box->tables as $table) {
            $length += \strlen((string) $table->sides()) + self::longestName($table);
        }

        return $length;
    }

    /**
     * Whether every draw of the box writes within 1000 characters.
     */
    public static function fits(LootBox $box): bool
    {
        return self::longest($box) <= self::MAX_LENGTH;
    }

    private static function longestName(LootTable $table): int
    {
        return max(array_map(static fn (LootEntry $entry): int => mb_strlen($entry->name, 'UTF-8'), $table->entries));
    }
}
