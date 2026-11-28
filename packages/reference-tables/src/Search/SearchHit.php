<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Search;

/**
 * Where a search found its text: a table's name or description, or a cell of one of its rows.
 */
final readonly class SearchHit
{
    /**
     * @param string  $table    the key of the table
     * @param ?int    $position the position of the row, from 0, in the table's `$rows`; null when the table's name or
     *                          description matched
     * @param ?string $column   the key of the column whose cell matched; null for a name, a description or a row's key
     * @param string  $text     the text that matched, in the locale searched
     */
    public function __construct(
        public string $table,
        public ?int $position,
        public ?string $column,
        public string $text,
    ) {
    }
}
