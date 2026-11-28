<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Import;

use Tilttek\GameCatalog\CollectionStatus;
use Tilttek\GameCatalog\ExternalId;

/**
 * A row of a collection file, read: the identifier of the game, its statuses, the priority of a wish and the plays.
 */
final readonly class CollectionRow
{
    /**
     * @param int                    $line     the number of the row in the file, the header being row 1
     * @param list<CollectionStatus> $statuses in the order of `CollectionStatus`
     */
    public function __construct(
        public int $line,
        public ExternalId $identifier,
        public array $statuses,
        public ?int $priority,
        public ?int $plays,
    ) {
    }
}
