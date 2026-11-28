<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Search;

use Tilttek\ReferenceTables\ColumnKind;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Internal\Fold;
use Tilttek\ReferenceTables\ReferenceTable;
use Tilttek\ReferenceTables\TableCatalogue;
use Tilttek\RpgSystems\Translations;

/**
 * Finds a text in the tables of a catalogue, in one locale: their names, their descriptions, the keys of their rows and
 * their text cells, without regard to case or to the accents of Latin letters. It reads the catalogue from start to end,
 * which suits the tables of one site; a search engine indexes a larger corpus from the same tables.
 */
final readonly class TableSearch
{
    /** The longest query, in characters. */
    public const int MAX_QUERY = 100;

    /** The most hits one search gives. */
    public const int MAX_HITS = 100;

    /**
     * The hits, table by table in the catalogue's order: the name, the description, then each row's key and its text
     * cells in the order of the columns.
     *
     * @param string  $query  1 to `MAX_QUERY` characters, spaces around it left out
     * @param int     $limit  1 to `MAX_HITS` hits
     * @param ?string $system the key of the one system searched; every table when null
     *
     * @return list<SearchHit>
     *
     * @throws InvalidTable when the query or the limit is out of its bounds
     */
    public function search(TableCatalogue $catalogue, string $query, string $locale = 'en', int $limit = 20, ?string $system = null): array
    {
        $query = trim($query);

        if ('' === $query || !mb_check_encoding($query, 'UTF-8') || mb_strlen($query) > self::MAX_QUERY) {
            throw InvalidTable::because(\sprintf('A query is 1 to %d characters of UTF-8.', self::MAX_QUERY));
        }

        if ($limit < 1 || $limit > self::MAX_HITS) {
            throw InvalidTable::because(\sprintf('A search gives 1 to %d hits.', self::MAX_HITS));
        }

        $needle = Fold::text($query);
        $hits = [];

        foreach ($catalogue->tables($system) as $table) {
            foreach ($this->texts($table, $locale) as [$position, $column, $text]) {
                if (str_contains(Fold::text($text), $needle)) {
                    $hits[] = new SearchHit($table->key, $position, $column, $text);

                    if (\count($hits) === $limit) {
                        return $hits;
                    }
                }
            }
        }

        return $hits;
    }

    /**
     * @return iterable<array{?int, ?string, string}>
     */
    private function texts(ReferenceTable $table, string $locale): iterable
    {
        yield [null, null, $table->name($locale)];

        if ($table->description instanceof Translations) {
            yield [null, null, $table->description->in($locale) ?? ''];
        }

        foreach ($table->rows as $position => $row) {
            if (null !== $row->key) {
                yield [$position, null, $row->key];
            }

            foreach ($table->columns as $column) {
                if (ColumnKind::Text === $column->kind) {
                    yield [$position, $column->key, $row->display($column->key, $locale)];
                }
            }
        }
    }
}
