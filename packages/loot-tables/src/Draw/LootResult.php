<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Draw;

use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootEntry;

/**
 * The entries a box gave: one pick per table, in the order of the tables, and where its faces come from.
 */
final readonly class LootResult
{
    /**
     * @param non-empty-list<Pick> $picks
     */
    private function __construct(
        public LootBox $box,
        public array $picks,
        public DrawSource $source,
    ) {
    }

    /**
     * The result of the faces the dice of the box showed, one per table.
     *
     * @param list<int> $faces
     *
     * @throws InvalidLoot when there is not one face per table, or a die has no such face
     */
    public static function ofFaces(LootBox $box, array $faces, DrawSource $source): self
    {
        if (\count($faces) !== \count($box->tables)) {
            throw InvalidLoot::because(\sprintf('The box %s rolls %d dice; %d faces were given.', $box->key, \count($box->tables), \count($faces)));
        }

        $picks = [];

        foreach ($box->tables as $index => $table) {
            $picks[] = new Pick($table, $faces[$index], $table->entry($faces[$index]));
        }

        return new self($box, $picks, $source);
    }

    /**
     * @return non-empty-list<int> the face of each die, in the order of the tables
     */
    public function faces(): array
    {
        return array_map(static fn (Pick $pick): int => $pick->face, $this->picks);
    }

    /**
     * @return non-empty-list<LootEntry> the entry of each table, in the order of the tables
     */
    public function entries(): array
    {
        return array_map(static fn (Pick $pick): LootEntry => $pick->entry, $this->picks);
    }
}
