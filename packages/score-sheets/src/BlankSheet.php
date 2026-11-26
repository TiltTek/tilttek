<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Exception\InvalidEntry;

/**
 * A blank sheet to print, as neutral data: one line by category — its label, whether it is entered, between which
 * bounds, or how it is computed —, a line for the total, the tie-breakers, and as many empty columns as asked. The
 * layout, in HTML or PDF, belongs to the application.
 */
final readonly class BlankSheet
{
    /**
     * @param int $columns the empty columns for the participants, 1 to 100
     *
     * @throws InvalidEntry when the columns are out of bounds
     */
    public function __construct(
        public SheetDefinition $sheet,
        public int $columns = 4,
    ) {
        if ($columns < 1 || $columns > Entries::MAX_PARTICIPANTS) {
            throw InvalidEntry::because(\sprintf('A blank sheet has 1 to %d columns.', Entries::MAX_PARTICIPANTS));
        }
    }

    /**
     * @return array{game: string, columns: int, lines: list<array<string, mixed>>, total: array{label: string, of: list<string>}, tiebreakers: list<array{category: string, prefer: string}>}
     */
    public function toArray(): array
    {
        $lines = [];
        $counted = [];

        foreach ($this->sheet->categories() as $category) {
            $lines[] = null === $category->operation
                ? ['key' => $category->key, 'label' => $category->label, 'entered' => true, 'counted' => $category->counted, 'min' => $category->min, 'max' => $category->max]
                : ['key' => $category->key, 'label' => $category->label, 'entered' => false, 'counted' => $category->counted, 'operation' => $category->operation->toArray()];

            if ($category->counted) {
                $counted[] = $category->key;
            }
        }

        return [
            'game' => $this->sheet->game,
            'columns' => $this->columns,
            'lines' => $lines,
            'total' => ['label' => 'total', 'of' => $counted],
            'tiebreakers' => array_map(static fn (Tiebreaker $tiebreaker): array => $tiebreaker->toArray(), $this->sheet->tiebreakers),
        ];
    }
}
