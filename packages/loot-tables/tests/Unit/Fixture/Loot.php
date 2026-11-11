<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit\Fixture;

use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;

/**
 * Tables and boxes for the tests: a table of any die whose entries are named after their faces.
 */
final class Loot
{
    private function __construct()
    {
    }

    public static function table(string $key, int $sides, string $name = 'Item'): LootTable
    {
        $entries = [];

        for ($face = 1; $face <= $sides; ++$face) {
            $entries[] = new LootEntry(\sprintf('%s-%d', $key, $face), \sprintf('%s %d', $name, $face), \sprintf('/loot/%s/%d.png', $key, $face), $key);
        }

        return new LootTable($key, ucfirst($key), $entries);
    }

    /**
     * @param list<int> $dice the sides of each table's die
     */
    public static function box(array $dice, string $key = 'box'): LootBox
    {
        $tables = [];

        foreach ($dice as $index => $sides) {
            $tables[] = self::table('t'.($index + 1), $sides);
        }

        return new LootBox($key, 'The box', $tables);
    }
}
