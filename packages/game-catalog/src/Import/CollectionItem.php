<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Import;

use Tilttek\GameCatalog\CollectionStatus;

/**
 * A game of a member's collection, found in the catalogue: the entry, its statuses, the priority of a wish and the
 * plays, for the application to keep on the member's shelf.
 */
final readonly class CollectionItem
{
    /**
     * @param list<CollectionStatus> $statuses
     */
    public function __construct(public string $entry, public array $statuses, public ?int $priority, public ?int $plays)
    {
    }
}
