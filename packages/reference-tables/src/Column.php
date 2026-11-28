<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables;

use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Internal\Check;
use Tilttek\RpgSystems\Translations;

/**
 * A column of a table: its key, its heading in each language, and what its cells hold.
 */
final readonly class Column
{
    /**
     * @param string $key the column's key in its table, `armor-class`, starting with a letter; never `key` nor `range`,
     *                    which name the row's own fields in a document
     *
     * @throws InvalidTable
     */
    public function __construct(
        public string $key,
        public Translations $names,
        public ColumnKind $kind = ColumnKind::Text,
    ) {
        Check::key($key, 'a column');

        if (is_numeric($key[0])) {
            throw InvalidTable::because(\sprintf('The key of the column "%s" must start with a letter.', $key));
        }

        if ('key' === $key || 'range' === $key) {
            throw InvalidTable::because(\sprintf('A column cannot be named "%s": the name is kept for the rows.', $key));
        }

        if ($names->isEmpty()) {
            throw InvalidTable::because(\sprintf('The column "%s" has no heading.', $key));
        }
    }

    /**
     * The heading in a locale, with the fallbacks of `Translations::in()`.
     */
    public function name(string $locale = 'en'): string
    {
        return $this->names->in($locale) ?? '';
    }
}
