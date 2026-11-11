<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Draw;

use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;

/**
 * One table of a result: the face its die showed and the entry of that face.
 */
final readonly class Pick
{
    public function __construct(
        public LootTable $table,
        public int $face,
        public LootEntry $entry,
    ) {
    }
}
