<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Rolling;

use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Result\PoolResult;
use Tilttek\ReferenceTables\Contract\TableObserver;
use Tilttek\ReferenceTables\Event\TableRolled;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Exception\LookupRefused;
use Tilttek\ReferenceTables\Exception\UnknownRow;
use Tilttek\ReferenceTables\Exception\UnknownTable;
use Tilttek\ReferenceTables\Range;
use Tilttek\ReferenceTables\ReferenceTable;
use Tilttek\ReferenceTables\Row;

/**
 * Rolls a table with its die through `tilttek/dice` and finds the row the total falls on, or finds the row of a die
 * rolled at the table; rolls the dice a cell holds. It keeps nothing between two calls: one serves every request.
 */
final readonly class TableRoller
{
    public function __construct(
        private DiceRoller $dice = new DiceRoller(),
        private ?TableObserver $observer = null,
    ) {
    }

    /**
     * Rolls the table's die and finds its row.
     *
     * @param int  $modifier added to the die before the lookup, from -`Range::LIMIT` to `Range::LIMIT`: a reaction roll
     *                       of 2d6 plus a bonus
     * @param bool $clamp    whether a total below the lowest range takes the first row, and one above the highest the
     *                       last, as many tables print it; a total in a gap of the table is refused either way
     *
     * @throws LookupRefused when the table has no die, or its die gives no number
     * @throws InvalidTable  when the modifier is beyond `Range::LIMIT`
     * @throws UnknownRow    when no row covers the total
     * @throws DiceException when the die exceeds the limits of the `DiceRoller`
     */
    public function roll(ReferenceTable $table, Randomizer $randomizer, int $modifier = 0, bool $clamp = false): TableRoll
    {
        $result = $this->dice->roll($table->pool(), $randomizer);
        $natural = $result->totals()[0] ?? throw LookupRefused::because(\sprintf('The die of the table "%s" gives no number.', $table->key));

        return $this->resolve($table, $natural, $modifier, $clamp, $result);
    }

    /**
     * Finds the row of a die rolled at the table, its value entered.
     *
     * @param int $natural what the die gave
     *
     * @throws LookupRefused when the table is looked up by key
     * @throws InvalidTable  when the value or the modifier is beyond `Range::LIMIT`
     * @throws UnknownRow    when no row covers the total
     */
    public function enter(ReferenceTable $table, int $natural, int $modifier = 0, bool $clamp = false): TableRoll
    {
        if (abs($natural) > Range::LIMIT) {
            throw InvalidTable::because(\sprintf('A value entered lies from %d to %d.', -Range::LIMIT, Range::LIMIT));
        }

        return $this->resolve($table, $natural, $modifier, $clamp, null);
    }

    /**
     * Rolls the dice a cell holds: the number appearing, the damage of a row.
     *
     * @throws UnknownTable  when the row has no cell in the column
     * @throws LookupRefused when the cell holds no dice
     * @throws DiceException when the dice exceed the limits of the `DiceRoller`
     */
    public function rollCell(Row $row, string $column, Randomizer $randomizer): PoolResult
    {
        return $this->dice->roll($row->cell($column)->pool(), $randomizer);
    }

    /**
     * @throws LookupRefused
     * @throws InvalidTable
     * @throws UnknownRow
     */
    private function resolve(ReferenceTable $table, int $natural, int $modifier, bool $clamp, ?PoolResult $dice): TableRoll
    {
        if (abs($modifier) > Range::LIMIT) {
            throw InvalidTable::because(\sprintf('A modifier lies from %d to %d.', -Range::LIMIT, Range::LIMIT));
        }

        $total = $natural + $modifier;
        $position = $table->position($total);
        $clamped = false;

        if (null === $position && $clamp) {
            $bounds = $table->bounds();
            $edge = max($bounds->min, min($bounds->max, $total));
            $clamped = $edge !== $total;
            $position = $table->position($edge);
        }

        if (null === $position) {
            throw UnknownRow::value($table->key, $total);
        }

        $this->observer?->notify(new TableRolled($table->key, $position, $total, !$dice instanceof PoolResult));

        return new TableRoll($table->key, $table->rows[$position], $position, $natural, $modifier, $total, $clamped, $dice);
    }
}
