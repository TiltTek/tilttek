<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Import;

/**
 * A collection file, read: the rows read, and the rows left out with their reasons — the first ones, up to the limit,
 * and how many more.
 */
final readonly class CollectionFile
{
    /**
     * @param list<CollectionRow> $rows
     * @param list<RowError>      $errors
     */
    public function __construct(public array $rows, public array $errors = [], public int $moreErrors = 0)
    {
    }
}
