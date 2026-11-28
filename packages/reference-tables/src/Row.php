<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables;

use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Exception\UnknownTable;
use Tilttek\ReferenceTables\Internal\Check;

/**
 * A row of a table: its key, in a table looked up by key, or the range it covers, in a table looked up by range, and
 * its cells by column. A cell left out is empty.
 */
final readonly class Row
{
    /**
     * @param array<string, Cell> $cells the cells by column key
     *
     * @throws InvalidTable when the key is not a key, or when the row has both a key and a range, or neither
     */
    public function __construct(
        public ?string $key,
        public ?Range $range,
        public array $cells,
    ) {
        if ((null === $key) === (!$range instanceof Range)) {
            throw InvalidTable::because('A row has a key or a range, not both nor neither.');
        }

        if (null !== $key) {
            Check::key($key, 'a row');
        }
    }

    /**
     * @param array<string, Cell> $cells
     *
     * @throws InvalidTable
     */
    public static function keyed(string $key, array $cells): self
    {
        return new self($key, null, $cells);
    }

    /**
     * @param array<string, Cell> $cells
     *
     * @throws InvalidTable
     */
    public static function ranged(Range $range, array $cells): self
    {
        return new self(null, $range, $cells);
    }

    /**
     * The row's key, or its range as a table prints it: `plate`, `31–45`.
     */
    public function label(): string
    {
        return $this->range instanceof Range ? $this->range->toString() : $this->key ?? '';
    }

    public function has(string $column): bool
    {
        return isset($this->cells[$column]);
    }

    /**
     * @throws UnknownTable when the row has no cell in the column
     */
    public function cell(string $column): Cell
    {
        return $this->cells[$column] ?? throw new UnknownTable(\sprintf('The row "%s" has no cell in the column "%s".', $this->label(), $column));
    }

    /**
     * The cell of a column in a locale, `Cell::display()`; an empty string when the cell is empty.
     */
    public function display(string $column, string $locale = 'en'): string
    {
        return ($this->cells[$column] ?? null)?->display($locale) ?? '';
    }
}
