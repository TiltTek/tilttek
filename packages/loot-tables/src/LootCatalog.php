<?php

declare(strict_types=1);

namespace Tilttek\LootTables;

use Tilttek\LootTables\Code\DrawText;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\Exception\UnknownLoot;

/**
 * The tables and the boxes of an application, by key. Every table of a box is one of the catalogue's, and every box
 * writes its draws within the 1000 characters of `DrawText`.
 */
final readonly class LootCatalog
{
    /**
     * @var array<string, LootTable>
     */
    private array $tables;

    /**
     * @var array<string, LootBox>
     */
    private array $boxes;

    /**
     * @param array<LootTable> $tables
     * @param array<LootBox>   $boxes
     *
     * @throws InvalidLoot
     */
    public function __construct(array $tables, array $boxes)
    {
        $byKey = [];

        foreach ($tables as $table) {
            if (isset($byKey[$table->key])) {
                throw InvalidLoot::because(\sprintf('The catalogue lists the table %s twice.', $table->key));
            }

            $byKey[$table->key] = $table;
        }

        $boxesByKey = [];

        foreach ($boxes as $box) {
            if (isset($boxesByKey[$box->key])) {
                throw InvalidLoot::because(\sprintf('The catalogue lists the box %s twice.', $box->key));
            }

            foreach ($box->tables as $table) {
                if (($byKey[$table->key] ?? null) !== $table) {
                    throw InvalidLoot::because(\sprintf('The box %s rolls the table %s, which is not one of the catalogue.', $box->key, $table->key));
                }
            }

            if (!DrawText::fits($box)) {
                throw InvalidLoot::because(\sprintf('The draws of the box %s may be written in %d characters; a draw is written in %d at most.', $box->key, DrawText::longest($box), DrawText::MAX_LENGTH));
            }

            $boxesByKey[$box->key] = $box;
        }

        $this->tables = $byKey;
        $this->boxes = $boxesByKey;
    }

    /**
     * @throws UnknownLoot
     */
    public function box(string $key): LootBox
    {
        return $this->boxes[$key] ?? throw UnknownLoot::box($key);
    }

    public function hasBox(string $key): bool
    {
        return isset($this->boxes[$key]);
    }

    /**
     * @throws UnknownLoot
     */
    public function table(string $key): LootTable
    {
        return $this->tables[$key] ?? throw UnknownLoot::table($key);
    }

    /**
     * @return list<LootBox> in the order they were given
     */
    public function boxes(): array
    {
        return array_values($this->boxes);
    }

    /**
     * @return list<LootTable> in the order they were given
     */
    public function tables(): array
    {
        return array_values($this->tables);
    }
}
