<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use DateTimeImmutable;

/**
 * A line of an entry's history: what changed it, which fields, when, and by whom — a member, or a source.
 */
final readonly class Change
{
    /**
     * @param list<Field> $fields the fields changed, when the change is of fields
     * @param ?string     $by     the member or the source that made the change
     * @param ?string     $about  what the change is about besides the fields: the erratum, the identifier, the barcode
     */
    public function __construct(
        public string $entry,
        public ChangeKind $kind,
        public DateTimeImmutable $at,
        public array $fields = [],
        public ?string $by = null,
        public ?string $about = null,
    ) {
    }
}
