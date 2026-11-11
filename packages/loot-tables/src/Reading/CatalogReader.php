<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Reading;

use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootCatalog;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;

/**
 * Reads a catalogue from plain arrays, as a decoded YAML or JSON document gives them:
 *
 * ```yaml
 * tables:
 *     headgear:
 *         name: Headgear
 *         entries:
 *             - { key: helmet, name: Helmet, image: /loot/helmet.png, slot: head }
 *             - { key: hood, name: Hood, slot: head }
 * boxes:
 *     starter:
 *         name: Starter box
 *         tables: [headgear, headgear]
 * ```
 *
 * Every value is checked: a key, a name or a reference it cannot read is refused with the path of the value.
 */
final readonly class CatalogReader
{
    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidLoot
     */
    public function read(array $data): LootCatalog
    {
        $tables = [];

        foreach ($this->map($data['tables'] ?? [], 'tables') as $key => $table) {
            $tables[$key] = $this->table((string) $key, $this->map($table, 'tables.'.$key));
        }

        $boxes = [];

        foreach ($this->map($data['boxes'] ?? [], 'boxes') as $key => $box) {
            $boxes[] = $this->box((string) $key, $this->map($box, 'boxes.'.$key), $tables);
        }

        return new LootCatalog($tables, $boxes);
    }

    /**
     * @param array<array-key, mixed> $table
     */
    private function table(string $key, array $table): LootTable
    {
        $entries = [];

        foreach ($this->list($table['entries'] ?? null, \sprintf('tables.%s.entries', $key)) as $index => $entry) {
            $path = \sprintf('tables.%s.entries.%d', $key, $index);
            $entry = $this->map($entry, $path);
            $entries[] = new LootEntry($this->string($entry['key'] ?? null, $path.'.key'), $this->string($entry['name'] ?? null, $path.'.name'), $this->optional($entry['image'] ?? null, $path.'.image'), $this->optional($entry['slot'] ?? null, $path.'.slot'));
        }

        return new LootTable($key, $this->string($table['name'] ?? null, \sprintf('tables.%s.name', $key)), $entries);
    }

    /**
     * @param array<array-key, mixed>     $box
     * @param array<array-key, LootTable> $tables
     */
    private function box(string $key, array $box, array $tables): LootBox
    {
        $rolled = [];

        foreach ($this->list($box['tables'] ?? null, \sprintf('boxes.%s.tables', $key)) as $index => $table) {
            $table = $this->string($table, \sprintf('boxes.%s.tables.%d', $key, $index));
            $rolled[] = $tables[$table] ?? throw InvalidLoot::because(\sprintf('The box %s rolls the table %s, which the catalogue does not declare.', $key, $table));
        }

        return new LootBox($key, $this->string($box['name'] ?? null, \sprintf('boxes.%s.name', $key)), $rolled);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function map(mixed $value, string $path): array
    {
        if (!\is_array($value)) {
            throw InvalidLoot::because(\sprintf('%s is a map.', $path));
        }

        return $value;
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value, string $path): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidLoot::because(\sprintf('%s is a list.', $path));
        }

        return $value;
    }

    private function string(mixed $value, string $path): string
    {
        return \is_string($value) ? $value : throw InvalidLoot::because(\sprintf('%s is a text.', $path));
    }

    private function optional(mixed $value, string $path): ?string
    {
        return null === $value ? null : $this->string($value, $path);
    }
}
