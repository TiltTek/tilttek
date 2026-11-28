<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables;

use Countable;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Exception\UnknownTable;
use Tilttek\RpgSystems\Provenance;

/**
 * The reference tables a site declares, each key once, in the order they were given: plain data an application builds
 * once and shares between requests.
 */
final readonly class TableCatalogue implements Countable
{
    /** The most tables one catalogue holds. */
    public const int MAX_TABLES = 2000;

    /** @var array<string, ReferenceTable> the tables by key, in their order */
    private array $tables;

    /**
     * @throws InvalidTable when a key is given twice or the catalogue holds more than `MAX_TABLES` tables
     */
    public function __construct(ReferenceTable ...$tables)
    {
        if (\count($tables) > self::MAX_TABLES) {
            throw InvalidTable::because(\sprintf('A catalogue holds %d tables at most.', self::MAX_TABLES));
        }

        $byKey = [];

        foreach ($tables as $table) {
            if (isset($byKey[$table->key])) {
                throw InvalidTable::because(\sprintf('The catalogue has the table "%s" twice.', $table->key));
            }

            $byKey[$table->key] = $table;
        }

        $this->tables = $byKey;
    }

    /**
     * The catalogue with more tables after its own.
     *
     * @throws InvalidTable
     */
    public function with(ReferenceTable ...$tables): self
    {
        return new self(...array_values($this->tables), ...$tables);
    }

    public function has(string $key): bool
    {
        return isset($this->tables[$key]);
    }

    /**
     * @throws UnknownTable
     */
    public function get(string $key): ReferenceTable
    {
        return $this->tables[$key] ?? throw UnknownTable::named($key);
    }

    /**
     * The tables, in their order; those of one system, or of one category, when asked.
     *
     * @return list<ReferenceTable>
     */
    public function tables(?string $system = null, ?string $category = null): array
    {
        return array_values(array_filter(
            $this->tables,
            static fn (ReferenceTable $table): bool => (null === $system || $system === $table->system) && (null === $category || $category === $table->category),
        ));
    }

    /**
     * The categories of the tables, each once, in the order they first appear.
     *
     * @return list<string>
     */
    public function categories(?string $system = null): array
    {
        $categories = [];

        foreach ($this->tables($system) as $table) {
            if (null !== $table->category) {
                $categories[$table->category] = $table->category;
            }
        }

        return array_values($categories);
    }

    /**
     * The provenances of the tables, each source once, for the attribution of a page: hand them to the
     * `AttributionRenderer` of `tilttek/rpg-systems`.
     *
     * @param list<string>|null $keys the tables a page shows; every table when null
     *
     * @return list<Provenance>
     *
     * @throws UnknownTable when a key names no table
     */
    public function provenances(?array $keys = null): array
    {
        $provenances = [];

        foreach (null === $keys ? $this->tables : array_map($this->get(...), $keys) as $table) {
            $provenances[$table->provenance->identity()] ??= $table->provenance;
        }

        return array_values($provenances);
    }

    public function count(): int
    {
        return \count($this->tables);
    }
}
