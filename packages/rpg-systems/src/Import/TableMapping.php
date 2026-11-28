<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Import;

use Tilttek\RpgSystems\Exception\InvalidRecord;
use Tilttek\RpgSystems\Text;

/**
 * One table of an extract and the fields its records are read from: the list of conditions, of skills, of ability
 * scores. A record's key in the system is the category, a dot, and the record's own key: `condition.blinded`.
 */
final readonly class TableMapping
{
    /**
     * @param string  $table        the name of the table in the extract, `conditions`
     * @param ?string $category     the category given to the terms of the table, `condition`; null for attributes
     * @param string  $key          the field holding the record's key, `index`
     * @param string  $name         the field holding its name
     * @param ?string $abbreviation the field holding its abbreviation, for attributes
     * @param ?string $description  the field holding its description, a text or a list of paragraphs
     *
     * @throws InvalidRecord
     */
    public function __construct(
        public string $table,
        public ?string $category = null,
        public string $key = 'index',
        public string $name = 'name',
        public ?string $abbreviation = null,
        public ?string $description = null,
    ) {
        if (null !== $category) {
            Text::key($category, 'the category of a table');
        }
    }
}
