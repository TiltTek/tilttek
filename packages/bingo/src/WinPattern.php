<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * A way to win: a name and the sets of cells any one of which, all ticked, wins. A row, a column, a diagonal, the full
 * card and the four corners are given; a custom pattern names its own sets — a letter, a frame, a cross.
 */
final readonly class WinPattern
{
    /** The most sets a pattern holds. */
    public const int MOST_SETS = 64;

    /**
     * @param list<list<int>> $sets
     */
    private function __construct(public string $name, public array $sets)
    {
    }

    /** Any full row. */
    public static function row(Grid $grid): self
    {
        $sets = [];

        for ($row = 0; $row < $grid->size; ++$row) {
            $sets[] = array_map(static fn (int $column): int => $grid->position($row, $column), range(0, $grid->size - 1));
        }

        return new self('row', $sets);
    }

    /** Any full column. */
    public static function column(Grid $grid): self
    {
        $sets = [];

        for ($column = 0; $column < $grid->size; ++$column) {
            $sets[] = array_map(static fn (int $row): int => $grid->position($row, $column), range(0, $grid->size - 1));
        }

        return new self('column', $sets);
    }

    /** Either diagonal. */
    public static function diagonal(Grid $grid): self
    {
        $last = $grid->size - 1;

        return new self('diagonal', [
            array_map(static fn (int $step): int => $grid->position($step, $step), range(0, $last)),
            array_map(static fn (int $step): int => $grid->position($step, $last - $step), range(0, $last)),
        ]);
    }

    /** Every cell of the card. */
    public static function fullCard(Grid $grid): self
    {
        return new self('full-card', [range(0, $grid->cells() - 1)]);
    }

    /** The four corners. */
    public static function fourCorners(Grid $grid): self
    {
        $last = $grid->size - 1;

        return new self('four-corners', [[$grid->position(0, 0), $grid->position(0, $last), $grid->position($last, 0), $grid->position($last, $last)]]);
    }

    /**
     * A pattern of the application's own: each set a list of cells of the grid, numbered row by row from 0.
     *
     * @param list<list<int>> $sets
     */
    public static function custom(string $name, Grid $grid, array $sets): self
    {
        Identifier::check($name, 'win pattern');
        $count = \count($sets);

        if ($count < 1 || $count > self::MOST_SETS) {
            throw InvalidBingo::because(\sprintf('The win pattern %s holds 1 to %d sets of cells; it holds %d.', $name, self::MOST_SETS, $count));
        }

        $checked = [];

        foreach ($sets as $set) {
            if ([] === $set) {
                throw InvalidBingo::because(\sprintf('A set of cells of the win pattern %s is empty.', $name));
            }

            foreach ($set as $cell) {
                if ($cell < 0 || $cell >= $grid->cells()) {
                    throw InvalidBingo::because(\sprintf('The win pattern %s names the cell %d, outside a card of %d cells.', $name, $cell, $grid->cells()));
                }
            }

            $checked[] = array_values(array_unique($set));
        }

        return new self($name, $checked);
    }

    /**
     * Whether one of the sets is all ticked.
     *
     * @param array<int, mixed> $ticked the cells ticked, as keys
     */
    public function matchedBy(array $ticked): bool
    {
        return array_any($this->sets, static fn (array $set): bool => [] === array_diff_key(array_flip($set), $ticked));
    }
}
