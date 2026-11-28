<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Export;

use Tilttek\GameShelf\Copy;
use Tilttek\GameShelf\CopyStatus;
use Tilttek\GameShelf\Note;
use Tilttek\GameShelf\ShelfView;
use Tilttek\GameShelf\Wish;
use Tilttek\GameShelf\WishKind;
use Tilttek\GameShelf\WishLevel;

/**
 * Writes what a viewer sees of a shelf as CSV, one file per part: the copies, the wishlist and the notes. Each file
 * starts with its header; every cell is quoted, and a cell a spreadsheet would read as a formula is written as text.
 * It writes the games by their identifiers in the catalogue: the application adds their names when it needs to.
 */
final readonly class ShelfCsv
{
    /** The first characters a spreadsheet reads as the start of a formula. */
    private const string FORMULA = '=+-@';

    public function copies(ShelfView $view): string
    {
        return $this->file(
            ['copy', 'game', 'statuses', 'edition', 'location', 'bringable'],
            array_map(static fn (Copy $copy): array => [
                $copy->id,
                $copy->game,
                implode(' ', array_map(static fn (CopyStatus $status): string => $status->value, $copy->statuses)),
                $copy->edition ?? '',
                $copy->location ?? '',
                $copy->bringable ? 'yes' : 'no',
            ], $view->copies),
        );
    }

    public function wishlist(ShelfView $view): string
    {
        return $this->file(
            ['rank', 'game', 'kinds', 'level'],
            array_map(static fn (int $rank, Wish $wish): array => [
                (string) ($rank + 1),
                $wish->game,
                implode(' ', array_map(static fn (WishKind $kind): string => $kind->value, $wish->kinds)),
                $wish->level instanceof WishLevel ? (string) $wish->level->value : '',
            ], array_keys($view->wishes), $view->wishes),
        );
    }

    public function notes(ShelfView $view): string
    {
        return $this->file(
            ['game', 'rating', 'plays', 'tags', 'comment'],
            array_map(static fn (Note $note): array => [
                $note->game,
                null === $note->rating ? '' : (string) $note->rating,
                (string) $note->plays,
                implode('; ', $note->tags),
                $note->comment ?? '',
            ], $view->notes),
        );
    }

    /**
     * @param list<string>       $header
     * @param list<list<string>> $rows
     */
    private function file(array $header, array $rows): string
    {
        $lines = [];

        foreach ([$header, ...$rows] as $row) {
            $lines[] = implode(',', array_map($this->cell(...), $row));
        }

        return implode("\r\n", $lines)."\r\n";
    }

    private function cell(string $value): string
    {
        if ('' !== $value && str_contains(self::FORMULA, $value[0])) {
            $value = "'".$value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }
}
