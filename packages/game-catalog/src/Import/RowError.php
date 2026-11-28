<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Import;

/**
 * A row of a collection file the reader left out, and why, for the member to fix.
 */
final readonly class RowError
{
    /**
     * @param int $line the number of the row in the file, the header being row 1
     */
    public function __construct(public int $line, public string $message)
    {
    }
}
