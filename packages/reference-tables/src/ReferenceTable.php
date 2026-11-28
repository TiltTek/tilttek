<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables;

use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Expression\Pool;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Exception\LookupRefused;
use Tilttek\ReferenceTables\Exception\UnknownRow;
use Tilttek\ReferenceTables\Exception\UnknownTable;
use Tilttek\ReferenceTables\Internal\Check;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Translations;

/**
 * A reference table of a game system, as a game master consults it during play: its columns, its rows found by key or
 * by range, the die it is rolled with when it has one, and where it comes from — source, licence, page.
 */
final readonly class ReferenceTable
{
    /** The most columns a table has. */
    public const int MAX_COLUMNS = 32;

    /** The most rows a table has. */
    public const int MAX_ROWS = 1000;

    /** The canonical notation of the d100, on which `00` stands for 100. */
    public const string PERCENTILE = '1d100';

    /** @var list<Column> */
    public array $columns;

    /** @var list<Row> the rows in the order the table prints them */
    public array $rows;

    /** The die the table is rolled with, in the canonical notation of `tilttek/dice`, `2d6`; null when it is not rolled. */
    public ?string $die;

    /** @var array<string, int> the columns' positions by key */
    private array $columnIndex;

    /** @var array<string, int> the rows' positions by key, in a table looked up by key */
    private array $keyIndex;

    /** @var list<array{int, int, int}> the ranges' minimum, maximum and row position, by minimum, in a table looked up by range */
    private array $rangeIndex;

    /**
     * @param string        $key         the table's key in its catalogue, `reaction`
     * @param array<Column> $columns     1 to `MAX_COLUMNS` columns, each key once, in order
     * @param array<Row>    $rows        1 to `MAX_ROWS` rows, in order: each with a key, once, in a table looked up by key; each with a
     *                                   range, none overlapping another, in a table looked up by range
     * @param ?string       $die         the die a table looked up by range is rolled with, one instruction of `tilttek/dice`,
     *                                   `2d6` or `d%`
     * @param ?string       $system      the key of the game system the table belongs to, `srd-5.2`
     * @param ?string       $category    the key of the table's category, to group tables on a screen, `combat`
     * @param ?string       $page        where the table is printed in its source, `p. 254`
     * @param ?Translations $description what the table is for, in each language
     *
     * @throws InvalidTable
     */
    public function __construct(
        public string $key,
        public Translations $names,
        public Provenance $provenance,
        array $columns,
        public Lookup $lookup,
        array $rows,
        ?string $die = null,
        public ?string $system = null,
        public ?string $category = null,
        public ?string $page = null,
        public ?Translations $description = null,
    ) {
        Check::key($key, 'a table');

        if ($names->isEmpty()) {
            throw InvalidTable::because(\sprintf('The table "%s" has no name.', $key));
        }

        if (null !== $system) {
            Check::key($system, \sprintf('the system of the table "%s"', $key));
        }

        if (null !== $category) {
            Check::key($category, \sprintf('the category of the table "%s"', $key));
        }

        if (null !== $page) {
            Check::label($page, \sprintf('The page of the table "%s"', $key));
        }

        $this->columns = array_values($columns);
        $this->columnIndex = $this->indexColumns();
        $this->rows = array_values($rows);
        [$this->keyIndex, $this->rangeIndex] = $this->indexRows();
        $this->die = null === $die ? null : $this->canonicalDie($die);
    }

    /**
     * The name in a locale, with the fallbacks of `Translations::in()`.
     */
    public function name(string $locale = 'en'): string
    {
        return $this->names->in($locale) ?? '';
    }

    public function hasColumn(string $key): bool
    {
        return isset($this->columnIndex[$key]);
    }

    /**
     * @throws UnknownTable when the table has no such column
     */
    public function column(string $key): Column
    {
        return $this->columns[$this->columnIndex[$key] ?? throw UnknownTable::column($this->key, $key)];
    }

    /**
     * Whether a table looked up by key holds a row; false for a table looked up by range.
     */
    public function hasRow(string $key): bool
    {
        return isset($this->keyIndex[$key]);
    }

    /**
     * The row of a key, in a table looked up by key.
     *
     * @throws LookupRefused when the table is looked up by range
     * @throws UnknownRow    when the table has no such row
     */
    public function row(string $key): Row
    {
        if (Lookup::Key !== $this->lookup) {
            throw LookupRefused::because(\sprintf('The table "%s" is looked up by range, not by key.', $this->key));
        }

        return $this->rows[$this->keyIndex[$key] ?? throw UnknownRow::key($this->key, $key)];
    }

    /**
     * The row whose range covers a value, in a table looked up by range.
     *
     * @throws LookupRefused when the table is looked up by key
     * @throws UnknownRow    when no range covers the value
     */
    public function lookup(int $value): Row
    {
        return $this->rows[$this->position($value) ?? throw UnknownRow::value($this->key, $value)];
    }

    /**
     * The position in `$rows`, from 0, of the row whose range covers a value; null when none does.
     *
     * @throws LookupRefused when the table is looked up by key
     */
    public function position(int $value): ?int
    {
        if (Lookup::Range !== $this->lookup) {
            throw LookupRefused::because(\sprintf('The table "%s" is looked up by key, not by range.', $this->key));
        }

        $low = 0;
        $high = \count($this->rangeIndex) - 1;

        while ($low <= $high) {
            $middle = ($low + $high) >> 1;
            [$min, $max, $position] = $this->rangeIndex[$middle];

            if ($value < $min) {
                $high = $middle - 1;
            } elseif ($value > $max) {
                $low = $middle + 1;
            } else {
                return $position;
            }
        }

        return null;
    }

    /**
     * The lowest and the highest value the ranges of a table looked up by range cover.
     *
     * @throws LookupRefused when the table is looked up by key
     */
    public function bounds(): Range
    {
        if (Lookup::Range !== $this->lookup) {
            throw LookupRefused::because(\sprintf('The table "%s" is looked up by key, not by range.', $this->key));
        }

        return new Range($this->rangeIndex[0][0], $this->rangeIndex[\count($this->rangeIndex) - 1][1]);
    }

    /**
     * The values between the lowest and the highest range that no row covers, as ranges in order: a mistake in a table
     * that is rolled, a level a table leaves out on purpose.
     *
     * @return list<Range>
     *
     * @throws LookupRefused when the table is looked up by key
     */
    public function gaps(): array
    {
        $gaps = [];
        $reached = $this->bounds()->min;

        foreach ($this->rangeIndex as [$min, $max]) {
            if ($min > $reached + 1) {
                $gaps[] = new Range($reached + 1, $min - 1);
            }

            $reached = $max;
        }

        return $gaps;
    }

    public function isRollable(): bool
    {
        return null !== $this->die;
    }

    /**
     * Whether the table is rolled with a d100, on which `00` stands for 100.
     */
    public function isPercentile(): bool
    {
        return self::PERCENTILE === $this->die;
    }

    /**
     * The table's die, read by `tilttek/dice` and ready to roll.
     *
     * @throws LookupRefused when the table has no die
     */
    public function pool(): Pool
    {
        return $this->roller()->parse($this->die ?? throw LookupRefused::because(\sprintf('The table "%s" has no die to roll.', $this->key)));
    }

    /**
     * @return array<string, int>
     *
     * @throws InvalidTable
     */
    private function indexColumns(): array
    {
        if ([] === $this->columns || \count($this->columns) > self::MAX_COLUMNS) {
            throw InvalidTable::because(\sprintf('The table "%s" has 1 to %d columns.', $this->key, self::MAX_COLUMNS));
        }

        $index = [];

        foreach ($this->columns as $position => $column) {
            if (isset($index[$column->key])) {
                throw InvalidTable::because(\sprintf('The table "%s" has the column "%s" twice.', $this->key, $column->key));
            }

            $index[$column->key] = $position;
        }

        return $index;
    }

    /**
     * The rows checked, and indexed by key or by range.
     *
     * @return array{array<string, int>, list<array{int, int, int}>}
     *
     * @throws InvalidTable
     */
    private function indexRows(): array
    {
        if ([] === $this->rows || \count($this->rows) > self::MAX_ROWS) {
            throw InvalidTable::because(\sprintf('The table "%s" has 1 to %d rows.', $this->key, self::MAX_ROWS));
        }

        $keys = [];
        $ranges = [];

        foreach ($this->rows as $position => $row) {
            $this->checkCells($row);

            if (Lookup::Key === $this->lookup) {
                $key = $row->key ?? throw InvalidTable::because(\sprintf('The row "%s" of the table "%s" needs a key: the table is looked up by key.', $row->label(), $this->key));

                if (isset($keys[$key])) {
                    throw InvalidTable::because(\sprintf('The table "%s" has the row "%s" twice.', $this->key, $key));
                }

                $keys[$key] = $position;
            } else {
                $range = $row->range ?? throw InvalidTable::because(\sprintf('The row "%s" of the table "%s" needs a range: the table is looked up by range.', $row->label(), $this->key));
                $ranges[] = [$range->min, $range->max, $position];
            }
        }

        usort($ranges, static fn (array $one, array $other): int => $one[0] <=> $other[0]);

        for ($next = 1, $count = \count($ranges); $next < $count; ++$next) {
            if ($ranges[$next][0] <= $ranges[$next - 1][1]) {
                throw InvalidTable::because(\sprintf('The rows "%s" and "%s" of the table "%s" overlap.', $this->rows[$ranges[$next - 1][2]]->label(), $this->rows[$ranges[$next][2]]->label(), $this->key));
            }
        }

        return [$keys, $ranges];
    }

    /**
     * @throws InvalidTable
     */
    private function checkCells(Row $row): void
    {
        foreach ($row->cells as $column => $cell) {
            $kind = $this->hasColumn($column) ? $this->column($column)->kind : throw InvalidTable::because(\sprintf('The row "%s" of the table "%s" has a cell in the column "%s", which the table does not have.', $row->label(), $this->key, $column));

            if ($kind !== $cell->kind) {
                throw InvalidTable::because(\sprintf('The cell "%s" of the row "%s" of the table "%s" holds a %s; the column holds a %s.', $column, $row->label(), $this->key, $cell->kind->value, $kind->value));
            }
        }
    }

    /**
     * @throws InvalidTable
     */
    private function canonicalDie(string $die): string
    {
        if (Lookup::Range !== $this->lookup) {
            throw InvalidTable::because(\sprintf('The table "%s" is looked up by key: only a table looked up by range is rolled.', $this->key));
        }

        $dice = $this->roller();

        try {
            $pool = $dice->parse($die);
        } catch (DiceException $diceException) {
            throw InvalidTable::because(\sprintf('The die "%s" of the table "%s" cannot be read: %s', $die, $this->key, $diceException->getMessage()));
        }

        if (1 !== \count($pool->expressions)) {
            throw InvalidTable::because(\sprintf('The die "%s" of the table "%s" must be one instruction.', $die, $this->key));
        }

        return $dice->write($pool);
    }

    private function roller(): DiceRoller
    {
        // A roller with the default limits keeps nothing between two calls: one serves every table.
        static $dice = new DiceRoller();

        return $dice;
    }
}
